<?php

/**
 * Ce que l'écran « Codes Non Payés » montre réellement au responsable groupage.
 *
 * Il tient encore son relevé dans un classeur alors que l'écran existe depuis
 * le 23/09/2026. Avant de le refaire, il faut savoir ce que l'écran rend sur
 * les vraies données : combien de codes impayés, répartis comment, et si le
 * groupe A1/A2/A3 de son classeur est renseigné en base.
 *
 * STRICTEMENT EN LECTURE. Il n'écrit rien, jamais.
 *
 * Usage : php app/Console/DiagnosticCodesNonPayes.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'exécute qu'en ligne de commande.\n");
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\nERREUR : " . $e->getMessage() . "\n");
    exit(3);
});

$config = require BASE_PATH . '/config/database.php';

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']),
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

const STATUTS = "('emise', 'partiellement_payee', 'en_retard')";

echo "=== LES CODES NON PAYÉS, SUR LES VRAIES DONNÉES ===\n\n";

$total = $pdo->query('
    SELECT COUNT(*) AS codes,
           COALESCE(SUM(f.montant_restant), 0) AS restant,
           COUNT(DISTINCT f.agence_id) AS agences
    FROM lbp_factures f
    JOIN lbp_colis c ON c.id = f.colis_id
    WHERE f.montant_restant > 0 AND f.statut IN ' . STATUTS
)->fetch();

printf(
    "  %d code(s) impayé(s), %s restant dû, sur %d agence(s).\n\n",
    (int) $total['codes'],
    number_format((float) $total['restant'], 0, ',', ' '),
    (int) $total['agences']
);

echo "=== PAR AGENCE ===\n\n";

$parAgence = $pdo->query('
    SELECT COALESCE(s.name, "Sans agence") AS agence,
           COUNT(*) AS codes,
           COALESCE(SUM(f.montant_restant), 0) AS restant
    FROM lbp_factures f
    JOIN lbp_colis c ON c.id = f.colis_id
    LEFT JOIN company_sites s ON s.id = f.agence_id
    WHERE f.montant_restant > 0 AND f.statut IN ' . STATUTS . '
    GROUP BY COALESCE(s.name, "Sans agence")
    ORDER BY SUM(f.montant_restant) DESC
')->fetchAll();

foreach ($parAgence as $ligne) {
    printf(
        "  %-36s %4d code(s)  %14s F\n",
        mb_substr((string) $ligne['agence'], 0, 36),
        (int) $ligne['codes'],
        number_format((float) $ligne['restant'], 0, ',', ' ')
    );
}

echo "\n=== PAR PRÉFIXE DE CODE ===\n\n";

foreach ($pdo->query('
    SELECT SUBSTRING_INDEX(c.numero_tracking, "-", 2) AS prefixe, COUNT(*) AS codes
    FROM lbp_factures f
    JOIN lbp_colis c ON c.id = f.colis_id
    WHERE f.montant_restant > 0 AND f.statut IN ' . STATUTS . '
    GROUP BY SUBSTRING_INDEX(c.numero_tracking, "-", 2)
    ORDER BY COUNT(*) DESC
')->fetchAll() as $ligne) {
    printf("  %-14s %4d code(s)\n", (string) $ligne['prefixe'], (int) $ligne['codes']);
}

/*
 * Le groupe A1/A2/A3 du classeur : l'écran existant sait l'affecter colis par
 * colis. S'il n'est renseigné nulle part, c'est que personne ne s'en est servi
 * — et c'est peut-etre la raison pour laquelle le classeur survit.
 */
echo "\n=== LE GROUPE A1 / A2 / A3 ===\n\n";

$aColonne = (bool) $pdo->query("SHOW COLUMNS FROM lbp_colis LIKE 'groupe_code'")->fetch();

if (!$aColonne) {
    echo "  La colonne groupe_code n'existe pas : elle sera créée au premier usage.\n";
} else {
    $groupes = $pdo->query('
        SELECT COALESCE(NULLIF(TRIM(c.groupe_code), ""), "(non renseigné)") AS groupe, COUNT(*) AS colis
        FROM lbp_colis c
        GROUP BY COALESCE(NULLIF(TRIM(c.groupe_code), ""), "(non renseigné)")
        ORDER BY COUNT(*) DESC
        LIMIT 20
    ')->fetchAll();

    foreach ($groupes as $ligne) {
        printf("  %-24s %5d colis\n", (string) $ligne['groupe'], (int) $ligne['colis']);
    }
}

echo "\n=== LES FACTURES EN DEVISE ÉTRANGÈRE ===\n\n";

foreach ($pdo->query('
    SELECT f.devise, COUNT(*) AS factures,
           COUNT(DISTINCT f.taux_change) AS taux_differents,
           MIN(f.taux_change) AS taux_min, MAX(f.taux_change) AS taux_max
    FROM lbp_factures f
    WHERE f.montant_restant > 0 AND f.statut IN ' . STATUTS . '
    GROUP BY f.devise
')->fetchAll() as $ligne) {
    printf(
        "  %-6s %4d facture(s)   %d taux différent(s)   de %s à %s\n",
        (string) $ligne['devise'],
        (int) $ligne['factures'],
        (int) $ligne['taux_differents'],
        (string) ($ligne['taux_min'] ?? '—'),
        (string) ($ligne['taux_max'] ?? '—')
    );
}

echo "\n  Ce script n'a rien écrit.\n";
