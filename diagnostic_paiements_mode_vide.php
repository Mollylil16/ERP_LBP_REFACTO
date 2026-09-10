<?php

declare(strict_types=1);

/**
 * DIAGNOSTIC — LECTURE SEULE. Ce script n'écrit rien, ne modifie rien.
 *
 * Objet : recenser les lignes de `lbp_paiements` dont le mode de règlement est vide,
 * et déterminer lesquelles correspondent à un règlement par portefeuille client.
 *
 * Contexte : facturePayerPortefeuille() écrivait `mode = 'portefeuille'` dans une
 * colonne ENUM qui n'acceptait pas cette valeur. Selon le `sql_mode` du serveur :
 *   - mode strict     : l'INSERT était rejeté, le règlement entier annulé (aucune ligne) ;
 *   - mode permissif  : la valeur était tronquée en chaîne vide et le montant comptabilisé
 *                       à tort dans les espèces en tiroir du point de caisse.
 *
 * Ce script sert à décider si un rattrapage `mode = 'portefeuille'` est justifié,
 * et sur quelles lignes exactement. Le rattrapage lui-même n'est PAS fait ici.
 *
 * Usage : php diagnostic_paiements_mode_vide.php
 */

define('BASE_PATH', __DIR__);

$config = require BASE_PATH . '/config/database.php';
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['host'],
    $config['port'],
    $config['dbname'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$line = static fn(): string => str_repeat('-', 78);
$money = static fn($v): string => number_format((float) $v, 0, ',', ' ') . ' XOF';

echo PHP_EOL . "DIAGNOSTIC — modes de règlement vides dans lbp_paiements (lecture seule)" . PHP_EOL;
echo $line() . PHP_EOL;

// ---------------------------------------------------------------------------
// 1. Configuration du serveur
// ---------------------------------------------------------------------------
$sqlMode = (string) $pdo->query("SELECT @@GLOBAL.sql_mode")->fetchColumn();
$sessionMode = (string) $pdo->query("SELECT @@SESSION.sql_mode")->fetchColumn();
$strict = str_contains($sqlMode, 'STRICT_TRANS_TABLES') || str_contains($sqlMode, 'STRICT_ALL_TABLES');

echo "1. CONFIGURATION SERVEUR" . PHP_EOL;
echo "   Base             : " . $config['dbname'] . ' @ ' . $config['host'] . PHP_EOL;
echo "   sql_mode GLOBAL  : " . ($sqlMode !== '' ? $sqlMode : '(vide)') . PHP_EOL;
echo "   sql_mode SESSION : " . ($sessionMode !== '' ? $sessionMode : '(vide)') . PHP_EOL;
echo "   Mode strict      : " . ($strict ? 'OUI' : 'NON') . PHP_EOL;
echo "   => " . ($strict
        ? "le règlement par portefeuille échouait entièrement ; aucune ligne vide attendue."
        : "les valeurs hors ENUM étaient tronquées ; des lignes vides sont attendues.") . PHP_EOL . PHP_EOL;

$colType = (string) $pdo->query("
    SELECT column_type FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'lbp_paiements' AND column_name = 'mode'
")->fetchColumn();

echo "   Type actuel de lbp_paiements.mode : " . $colType . PHP_EOL;
echo "   ENUM élargi déjà appliqué         : " . (str_contains($colType, "'portefeuille'") ? 'OUI' : 'NON') . PHP_EOL . PHP_EOL;

// ---------------------------------------------------------------------------
// 2. Répartition de toutes les valeurs de `mode`
// ---------------------------------------------------------------------------
echo "2. RÉPARTITION DES MODES ENREGISTRÉS" . PHP_EOL;
$repartition = $pdo->query("
    SELECT
        CASE WHEN mode IS NULL THEN '(NULL)' WHEN mode = '' THEN '(VIDE)' ELSE mode END AS valeur,
        COUNT(*)     AS nb,
        SUM(montant) AS total
    FROM lbp_paiements
    GROUP BY valeur
    ORDER BY nb DESC
")->fetchAll();

if ($repartition === []) {
    echo "   Aucun paiement en base." . PHP_EOL . PHP_EOL;
} else {
    printf("   %-20s %8s  %18s" . PHP_EOL, 'MODE', 'LIGNES', 'MONTANT');
    foreach ($repartition as $r) {
        printf("   %-20s %8d  %18s" . PHP_EOL, $r['valeur'], (int) $r['nb'], $money($r['total']));
    }
    echo PHP_EOL;
}

// ---------------------------------------------------------------------------
// 3. Lignes vides, croisées avec les débits de portefeuille client
// ---------------------------------------------------------------------------
echo "3. LIGNES À MODE VIDE, CROISÉES AVEC LES DÉBITS DE PORTEFEUILLE" . PHP_EOL;
echo "   Un débit de portefeuille est tracé dans lbp_client_wallet_transactions avec" . PHP_EOL;
echo "   mode_paiement = 'Portefeuille Client' et reference_transac = n° de facture." . PHP_EOL;
echo "   Le champ `type` n'est pas fiable pour ce croisement : le code y écrivait 'DEBIT'," . PHP_EOL;
echo "   valeur absente de son ENUM, donc stockée vide sur les lignes existantes." . PHP_EOL . PHP_EOL;

$walletTableExists = (int) $pdo->query("
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'lbp_client_wallet_transactions'
")->fetchColumn() > 0;

if (!$walletTableExists) {
    echo "   Table lbp_client_wallet_transactions absente : croisement impossible." . PHP_EOL;
    echo "   Aucun rattrapage ne peut être justifié sur cette base." . PHP_EOL . PHP_EOL;
    exit(0);
}

$suspects = $pdo->query("
    SELECT
        p.id            AS paiement_id,
        p.date_paiement,
        p.montant,
        p.devise,
        p.type,
        p.mode_paiement,
        f.numero_facture,
        f.agence_id,
        cl.name         AS client_nom,
        wt.id           AS wallet_tx_id,
        wt.montant_xof  AS wallet_montant,
        wt.motif        AS wallet_motif
    FROM lbp_paiements p
    JOIN lbp_factures f  ON p.facture_id = f.id
    JOIN lbp_clients cl  ON f.client_id = cl.id
    LEFT JOIN lbp_client_wallet_transactions wt
           ON wt.reference_transac = f.numero_facture
          AND ABS(wt.montant_xof - p.montant) < 0.01
          AND wt.mode_paiement = 'Portefeuille Client'
          AND (wt.type = 'DEBIT_FACTURE' OR wt.type = '' OR wt.type IS NULL)
    WHERE p.mode IS NULL OR p.mode = ''
    ORDER BY p.date_paiement ASC
")->fetchAll();

if ($suspects === []) {
    echo "   Aucune ligne à mode vide : rien à rattraper." . PHP_EOL . PHP_EOL;
    echo $line() . PHP_EOL;
    echo "CONCLUSION : la base est saine sur ce point. Seul l'élargissement de l'ENUM" . PHP_EOL;
    echo "était nécessaire, pour que les prochains règlements par portefeuille aboutissent." . PHP_EOL . PHP_EOL;
    exit(0);
}

$confirmes = [];
$ambigus = [];
foreach ($suspects as $s) {
    if ($s['wallet_tx_id'] !== null) {
        $confirmes[] = $s;
    } else {
        $ambigus[] = $s;
    }
}

printf("   %-6s %-19s %14s %-22s %-24s" . PHP_EOL, 'ID', 'DATE', 'MONTANT', 'FACTURE', 'CLIENT');
foreach ($suspects as $s) {
    printf(
        "   %-6d %-19s %14s %-22s %-24s %s" . PHP_EOL,
        (int) $s['paiement_id'],
        (string) $s['date_paiement'],
        $money($s['montant']),
        (string) $s['numero_facture'],
        mb_strimwidth((string) $s['client_nom'], 0, 24),
        $s['wallet_tx_id'] !== null ? '<- débit portefeuille confirmé' : '<- AUCUNE correspondance'
    );
}

$totalConfirme = array_sum(array_map(static fn($s) => (float) $s['montant'], $confirmes));
$totalAmbigu = array_sum(array_map(static fn($s) => (float) $s['montant'], $ambigus));

echo PHP_EOL . $line() . PHP_EOL;
echo "SYNTHÈSE" . PHP_EOL;
echo "   Lignes à mode vide          : " . count($suspects) . PHP_EOL;
echo "   Confirmées « portefeuille » : " . count($confirmes) . '  (' . $money($totalConfirme) . ')' . PHP_EOL;
echo "   Sans correspondance         : " . count($ambigus) . '  (' . $money($totalAmbigu) . ')' . PHP_EOL . PHP_EOL;

echo "IMPACT SUR LES POINTS DE CAISSE" . PHP_EOL;
echo "   Ces montants sont aujourd'hui comptés dans « Espèces (Tiroir) », donc attendus" . PHP_EOL;
echo "   en liquide lors du comptage physique. Ils créent un écart de caisse négatif" . PHP_EOL;
echo "   au détriment des caissières, sur les journées suivantes :" . PHP_EOL . PHP_EOL;

$parJour = $pdo->query("
    SELECT DATE(p.date_paiement) AS jour, f.agence_id, COUNT(*) AS nb, SUM(p.montant) AS total
    FROM lbp_paiements p
    JOIN lbp_factures f ON p.facture_id = f.id
    WHERE p.mode IS NULL OR p.mode = ''
    GROUP BY jour, f.agence_id
    ORDER BY jour ASC
")->fetchAll();

printf("   %-12s %-10s %8s %18s" . PHP_EOL, 'JOUR', 'AGENCE', 'LIGNES', 'MONTANT');
foreach ($parJour as $j) {
    printf("   %-12s %-10s %8d %18s" . PHP_EOL, $j['jour'], '#' . $j['agence_id'], (int) $j['nb'], $money($j['total']));
}

echo PHP_EOL . $line() . PHP_EOL;
echo "DÉCISION À PRENDRE" . PHP_EOL;

if ($confirmes !== []) {
    echo '   ' . count($confirmes) . " ligne(s) sont formellement rattachées à un débit de portefeuille." . PHP_EOL;
    echo "   Un rattrapage vers mode = 'portefeuille' est justifié pour celles-ci :" . PHP_EOL;
    echo '   ids = ' . implode(', ', array_map(static fn($s) => (string) $s['paiement_id'], $confirmes)) . PHP_EOL;
}

if ($ambigus !== []) {
    echo PHP_EOL . '   ' . count($ambigus) . " ligne(s) n'ont AUCUNE correspondance en portefeuille." . PHP_EOL;
    echo "   Origine inconnue : ne pas les modifier sans les avoir examinées une par une." . PHP_EOL;
}

echo PHP_EOL . "   Aucune écriture n'a été faite par ce script." . PHP_EOL . PHP_EOL;
