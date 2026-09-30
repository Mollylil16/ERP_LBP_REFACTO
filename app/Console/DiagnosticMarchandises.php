<?php

/**
 * Pourquoi la facture affiche « MARCHANDISES DIVERSES » au lieu des produits.
 * LECTURE SEULE — aucune écriture.
 *
 * La description d'une ligne vient, dans l'ordre : du nom saisi par l'agent,
 * des produits cochés dans la liste, et faute des deux, d'un libellé générique.
 * Signalé par la direction le 30/09/2026 sur les factures LB-CI-465, 475 et 476,
 * où chaque ligne portait ce libellé alors que les agences disent avoir choisi
 * leurs produits.
 *
 * Ce script mesure, sur les données réelles, laquelle des trois voies sert
 * vraiment : si le référentiel produits est vide ou inactif, aucune agence ne
 * peut rien choisir, et le libellé générique est le seul possible.
 *
 * Usage :
 *   php app/Console/DiagnosticMarchandises.php
 *   php app/Console/DiagnosticMarchandises.php --jours=30
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
    fwrite(STDERR, 'Dans ' . basename($e->getFile()) . ' ligne ' . $e->getLine() . "\n");
    exit(3);
});

$arguments = $argv ?? [];
$jours = 30;

foreach ($arguments as $argument) {
    if (preg_match('/^--jours=(\d+)$/', $argument, $t)) {
        $jours = max(1, (int) $t[1]);
    }
}

$config = require BASE_PATH . '/config/database.php';

try {
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
} catch (PDOException $e) {
    fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . "\n");
    exit(2);
}

$colonne = static function (?string $texte, int $largeur): string {
    $texte = mb_substr((string) ($texte ?? '—'), 0, $largeur);

    return $texte . str_repeat(' ', max(0, $largeur - mb_strlen($texte)));
};

$depuis = date('Y-m-d', strtotime('-' . $jours . ' days'));

echo "=== D'OÙ VIENT « MARCHANDISES DIVERSES » ===\n";
echo 'Depuis le ' . $depuis . "\n\n";

// ---------------------------------------------------------------------------
// 1. Le référentiel produits. Vide, il ne laisse aucun choix à l'agent.
// ---------------------------------------------------------------------------

$produits = $pdo->query('SELECT COUNT(*) AS total, SUM(actif = 1) AS actifs FROM lbp_produits')->fetch();

printf("Référentiel produits : %d au total, %d actif(s).\n", (int) $produits['total'], (int) $produits['actifs']);

if ((int) $produits['actifs'] === 0) {
    echo "  → La liste déroulante des produits est vide : aucune agence ne peut y choisir quoi que ce soit.\n";
    echo "    C'est la cause, et elle se corrige au référentiel, pas dans le logiciel.\n";
}

$recents = $pdo->query("
    SELECT nom, prix_unitaire, actif, created_at
    FROM lbp_produits
    ORDER BY id DESC
    LIMIT 8
")->fetchAll();

if ($recents !== []) {
    echo "\n  Derniers produits enregistrés :\n";
    foreach ($recents as $p) {
        printf(
            "    %s %12s  %s\n",
            $colonne((string) $p['nom'], 34),
            number_format((float) $p['prix_unitaire'], 0, ',', ' '),
            $p['actif'] ? 'actif' : 'inactif'
        );
    }
}

// ---------------------------------------------------------------------------
// 2. Ce que portent réellement les lignes de marchandise.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS lignes,
           SUM(m.description = 'MARCHANDISES DIVERSES') AS generiques,
           SUM(m.description IS NULL OR m.description = '') AS vides,
           COUNT(DISTINCT m.colis_id) AS colis
    FROM lbp_marchandises m
    JOIN lbp_colis c ON c.id = m.colis_id
    WHERE DATE(c.created_at) >= :depuis
");
$stmt->execute(['depuis' => $depuis]);
$compte = $stmt->fetch();

printf(
    "\nLignes de marchandise : %d sur %d colis. %d portent le libellé générique",
    (int) $compte['lignes'],
    (int) $compte['colis'],
    (int) $compte['generiques']
);
printf(
    " (%s).\n",
    (int) $compte['lignes'] > 0
        ? number_format((int) $compte['generiques'] * 100 / (int) $compte['lignes'], 0) . ' %'
        : '—'
);

// ---------------------------------------------------------------------------
// 3. Par agence : qui nomme sa marchandise, qui ne la nomme pas.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT s.name AS agence,
           COUNT(*) AS lignes,
           SUM(m.description = 'MARCHANDISES DIVERSES') AS generiques
    FROM lbp_marchandises m
    JOIN lbp_colis c ON c.id = m.colis_id
    LEFT JOIN company_sites s ON s.id = c.agence_depart_id
    WHERE DATE(c.created_at) >= :depuis
    GROUP BY s.id, s.name
    ORDER BY generiques DESC
");
$stmt->execute(['depuis' => $depuis]);
$parAgence = $stmt->fetchAll();

if ($parAgence !== []) {
    echo "\n  " . $colonne('AGENCE', 30) . "  LIGNES  GÉNÉRIQUES\n";
    echo '  ' . str_repeat('-', 52) . "\n";

    foreach ($parAgence as $a) {
        printf(
            "  %s %7d %11d\n",
            $colonne((string) $a['agence'], 30),
            (int) $a['lignes'],
            (int) $a['generiques']
        );
    }
}

// ---------------------------------------------------------------------------
// 4. Les dernières lignes, telles qu'elles sont parties sur les factures.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT c.numero_tracking, c.created_at, u.full_name AS agent,
           m.description, m.poids_unitaire, m.prix_kg, m.emballage, m.nbre_etiquettes
    FROM lbp_marchandises m
    JOIN lbp_colis c ON c.id = m.colis_id
    LEFT JOIN users u ON u.id = c.created_by
    WHERE DATE(c.created_at) >= :depuis
    ORDER BY m.id DESC
    LIMIT 15
");
$stmt->execute(['depuis' => $depuis]);
$lignes = $stmt->fetchAll();

if ($lignes !== []) {
    echo "\n=== LES QUINZE DERNIÈRES LIGNES ===\n\n";
    printf(
        "  %s %s %s %8s %9s %s\n",
        $colonne('COLIS', 14),
        $colonne('AGENT', 20),
        $colonne('DESCRIPTION', 26),
        'POIDS',
        'PRIX/KG',
        'EMBALLAGE'
    );
    echo '  ' . str_repeat('-', 100) . "\n";

    foreach ($lignes as $l) {
        printf(
            "  %s %s %s %8s %9s %s\n",
            $colonne((string) $l['numero_tracking'], 14),
            $colonne((string) ($l['agent'] ?? '—'), 20),
            $colonne((string) $l['description'], 26),
            number_format((float) $l['poids_unitaire'], 1, ',', ' '),
            number_format((float) $l['prix_kg'], 0, ',', ' '),
            (string) ($l['emballage'] ?? '—')
        );
    }
}

echo "\nLecture :\n";
echo "  - une ligne nommée montre que la voie « nom saisi » ou « produits cochés » fonctionne ;\n";
echo "  - un référentiel actif mais des lignes toutes génériques veut dire que la liste\n";
echo "    n'est pas utilisée au comptoir, et non que le logiciel perd le choix ;\n";
echo "  - un référentiel vide explique tout à lui seul.\n";
echo "\nTerminé. Aucune donnée n'a été modifiée.\n";
