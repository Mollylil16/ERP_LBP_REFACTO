<?php

/**
 * Ce que le logiciel sait des caisses, et ce qu'il n'en sait pas.
 *
 * L'écran Mouvements de caisse exige une caisse pour enregistrer un versement
 * ou un retrait. Si une agence n'en a aucune, sa caissière voit un sélecteur
 * vide et l'enregistrement lui est refusé — sans que rien ne lui dise
 * pourquoi. Ce script répond avant qu'on le découvre au guichet.
 *
 * STRICTEMENT EN LECTURE. Il n'écrit rien, jamais.
 *
 * Usage : php app/Console/DiagnosticCaisses.php
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
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

echo "=== LES CAISSES DÉCLARÉES ===\n\n";

$colonnes = [];
foreach ($pdo->query("SHOW COLUMNS FROM lbp_caisses") as $colonne) {
    $colonnes[] = (string) $colonne['Field'];
}

echo '  Colonnes : ' . implode(', ', $colonnes) . "\n\n";

$aNom = in_array('nom', $colonnes, true);

$caisses = $pdo->query('
    SELECT c.*, s.name AS agence, s.is_active
    FROM lbp_caisses c
    LEFT JOIN company_sites s ON s.id = c.agency_id
    ORDER BY c.agency_id, c.id
')->fetchAll();

if ($caisses === []) {
    echo "  AUCUNE CAISSE EN BASE.\n";
    echo "  Aucune agence ne peut enregistrer de mouvement : le sélecteur est vide partout.\n\n";
} else {
    printf("  %-5s %-34s %-28s %s\n", 'N°', 'NOM', 'AGENCE', 'ÉTAT');
    echo '  ' . str_repeat('-', 86) . "\n";

    foreach ($caisses as $caisse) {
        $agence = $caisse['agence'] ?? null;
        $etat = $agence === null
            ? 'AGENCE SUPPRIMÉE — caisse inutilisable'
            : (((int) ($caisse['is_active'] ?? 0)) === 1 ? 'utilisable' : 'agence inactive');

        printf(
            "  %-5s %-34s %-28s %s\n",
            (int) $caisse['id'],
            $aNom ? mb_substr((string) ($caisse['nom'] ?? ''), 0, 34) : '(table sans colonne nom)',
            mb_substr((string) ($agence ?? ('n° ' . $caisse['agency_id'])), 0, 28),
            $etat
        );
    }
    echo "\n";
}

echo "=== LES AGENCES ACTIVES, ET LEUR CAISSE ===\n\n";

$agences = $pdo->query('
    SELECT s.id, s.name,
           (SELECT COUNT(*) FROM lbp_caisses c WHERE c.agency_id = s.id) AS caisses,
           (SELECT COUNT(*) FROM users u WHERE u.agence_id = s.id) AS comptes
    FROM company_sites s
    WHERE s.is_active = 1
    ORDER BY s.name
')->fetchAll();

$sansCaisse = 0;

printf("  %-34s %-10s %-10s %s\n", 'AGENCE', 'CAISSES', 'COMPTES', 'SAISIE POSSIBLE');
echo '  ' . str_repeat('-', 80) . "\n";

foreach ($agences as $agence) {
    $nb = (int) $agence['caisses'];

    if ($nb === 0) {
        $sansCaisse++;
    }

    printf(
        "  %-34s %-10d %-10d %s\n",
        mb_substr((string) $agence['name'], 0, 34),
        $nb,
        (int) $agence['comptes'],
        $nb > 0 ? 'oui' : 'NON — sélecteur vide, enregistrement refusé'
    );
}

echo "\n";

// L'index unique : c'est lui qui décide si une agence peut tenir deux caisses,
// et la migration prétend le retirer sous un nom qu'il ne porte peut-être pas.
echo "=== L'INDEX UNIQUE SUR L'AGENCE ===\n\n";

$index = $pdo->query("
    SELECT index_name, column_name
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'lbp_caisses'
      AND non_unique = 0
    ORDER BY index_name, seq_in_index
")->fetchAll();

if ($index === []) {
    echo "  Aucun index unique : une agence peut tenir plusieurs caisses.\n\n";
} else {
    foreach ($index as $ligne) {
        printf("  %-34s sur %s\n", (string) $ligne['index_name'], (string) $ligne['column_name']);
    }
    echo "\n  Tant qu'un unique porte sur agency_id, une agence ne peut tenir qu'une caisse.\n\n";
}

echo "=== CE QU'IL FAUT RETENIR ===\n\n";

if ($sansCaisse > 0) {
    echo '  ' . $sansCaisse . " agence(s) active(s) sans aucune caisse : la saisie y est impossible.\n";
} else {
    echo "  Chaque agence active tient au moins une caisse.\n";
}

echo "\n  Ce script n'a rien écrit.\n";
