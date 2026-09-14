<?php

/**
 * Remet en brouillon des points de caisse soumis, pour qu'ils soient redeclares.
 *
 * Usage :
 *   php app/Console/RouvrirPointsCaisse.php --agence=3403 --jours=2026-09-08,2026-09-09
 *   php app/Console/RouvrirPointsCaisse.php --agence=3403 --jours=2026-09-08,2026-09-09 --appliquer
 *
 * Pourquoi
 * --------
 * Une fois soumis, un point ne peut plus etre redeclare depuis l'ecran : le
 * controleur refuse avec « Le point de caisse de ce jour a deja ete soumis ».
 * C'est voulu pour le travail quotidien. Mais quand un point a ete soumis sur
 * des chiffres faux - ici des factures et des reglements dates du lendemain par
 * le logiciel - la seule correction honnete est de laisser la caissiere
 * redeclarer son comptage sur les chiffres justes. Aucun script ne doit
 * inventer un comptage a sa place.
 *
 * Ce qui est remis a zero
 * -----------------------
 * La declaration : comptage physique, ecart, explication, decompte des coupures,
 * date et caractere retroactif de la soumission. Le point repasse en brouillon
 * et reapparait dans l'ecran des points de caisse, pret a etre soumis.
 *
 * Ce qui est conserve
 * -------------------
 * Tout ce qui est efface est d'abord inscrit dans lbp_audit_logs, ligne entiere,
 * avec le chainage SHA-256 que verifie VerifyAuditIntegrity. La declaration
 * d'origine reste donc consultable et on ne peut pas la faire disparaitre
 * discretement.
 *
 * Ce qui est refuse
 * -----------------
 * Un point consolide par le chef d'agence : le rouvrir desavouerait une
 * validation humaine, ce n'est pas a un script de le decider.
 *
 * A lancer APRES CorrigerDatesEncaissements.php : sinon la caissiere
 * redeclarerait sur les memes chiffres faux.
 *
 * Sans --appliquer, AUCUNE ecriture n'est faite. Avant d'appliquer :
 *   mysqldump -u UTILISATEUR -p BASE lbp_etats_journaliers > ~/rapports/points-avant-reouverture.sql
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'execute qu'en ligne de commande.\n");
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

// Connexion directe, sans bootstrap/app.php : celui-ci lance le MigrationRunner,
// qui execute du DDL a chaque appel.
$config = require BASE_PATH . '/config/database.php';

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'] ?? 3306,
        $config['dbname'],
        $config['charset'] ?? 'utf8mb4'
    ),
    $config['username'],
    $config['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$arguments = $argv ?? [];
$appliquer = in_array('--appliquer', $arguments, true);
$agence = 0;
$jours = [];

foreach ($arguments as $argument) {
    if (preg_match('/^--agence=(\d+)$/', $argument, $trouve)) {
        $agence = (int) $trouve[1];
    }
    if (preg_match('/^--jours=([0-9,\-]+)$/', $argument, $trouve)) {
        foreach (explode(',', $trouve[1]) as $jour) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour)) {
                $jours[] = $jour;
            }
        }
    }
}

$ligne = static fn (string $c = '-'): string => str_repeat($c, 78);
$fr = static fn (mixed $m): string => $m === null ? '—' : number_format((float) $m, 0, ',', ' ');

if ($agence === 0 || $jours === []) {
    echo "Usage : php app/Console/RouvrirPointsCaisse.php --agence=ID --jours=AAAA-MM-JJ[,AAAA-MM-JJ...]\n";
    echo "Les deux options sont obligatoires : on ne rouvre jamais un point par defaut.\n";
    exit(1);
}

$jours = array_values(array_unique($jours));
sort($jours);

echo $ligne('=') . PHP_EOL;
echo 'REOUVERTURE DE POINTS DE CAISSE SOUMIS' . PHP_EOL;
echo $ligne('=') . PHP_EOL;
echo 'Base      : ' . $config['dbname'] . ' sur ' . $config['host'] . PHP_EOL;
echo 'Agence    : ' . $agence . PHP_EOL;
echo 'Jours     : ' . implode(', ', $jours) . PHP_EOL;
echo 'Mode      : ' . ($appliquer ? '*** ECRITURE ***' : 'analyse seule, rien ne sera ecrit') . PHP_EOL;
echo $ligne('=') . PHP_EOL . PHP_EOL;

$marqueurs = implode(',', array_fill(0, count($jours), '?'));
$stmt = $pdo->prepare("
    SELECT e.*, s.name AS agence_name
    FROM lbp_etats_journaliers e
    LEFT JOIN company_sites s ON s.id = e.agence_id
    WHERE e.agence_id = ? AND e.date_jour IN ({$marqueurs})
    ORDER BY e.date_jour ASC
");
$stmt->execute([$agence, ...$jours]);
$points = $stmt->fetchAll();

$trouves = array_column($points, 'date_jour');
$aRouvrir = [];

printf("%-12s %-10s %-11s %14s %14s %12s  %s" . PHP_EOL, 'jour', 'statut', 'retroactif', 'theorique', 'declare', 'ecart', 'soumis le');
echo $ligne() . PHP_EOL;

foreach ($points as $p) {
    printf(
        "%-12s %-10s %-11s %14s %14s %12s  %s" . PHP_EOL,
        $p['date_jour'],
        $p['statut'],
        (int) $p['soumission_retroactive'] === 1 ? 'oui' : 'non',
        $fr($p['solde_caisse_agence_xof']),
        $fr($p['solde_physique_declare']),
        $fr($p['ecart_caisse']),
        $p['date_soumission'] ?? '—'
    );

    if ((string) ($p['explication_ecart'] ?? '') !== '') {
        echo '             explication : ' . $p['explication_ecart'] . PHP_EOL;
    }

    if ($p['statut'] === 'soumis') {
        $aRouvrir[] = $p;
    }
}

foreach ($jours as $jour) {
    if (!in_array($jour, $trouves, true)) {
        printf("%-12s %s" . PHP_EOL, $jour, 'aucun point enregistre : rien a rouvrir, il se soumet normalement');
    }
}

echo PHP_EOL;

foreach ($points as $p) {
    if ($p['statut'] === 'consolide') {
        echo 'REFUS pour le ' . $p['date_jour'] . ' : point consolide par le chef d\'agence.' . PHP_EOL;
        echo '  Le rouvrir desavouerait une validation humaine ; a decider hors script.' . PHP_EOL;
    }
    if ($p['statut'] === 'brouillon') {
        echo 'Le ' . $p['date_jour'] . ' est deja en brouillon : rien a faire.' . PHP_EOL;
    }
}

if ($aRouvrir === []) {
    echo 'Aucun point soumis a rouvrir.' . PHP_EOL;
    echo $ligne('=') . PHP_EOL;
    exit(0);
}

echo count($aRouvrir) . ' point(s) seraient remis en brouillon.' . PHP_EOL;
echo 'La declaration de chacun sera d\'abord inscrite dans lbp_audit_logs.' . PHP_EOL . PHP_EOL;

if (!$appliquer) {
    echo $ligne('=') . PHP_EOL;
    echo "ANALYSE SEULE : rien n'a ete ecrit." . PHP_EOL . PHP_EOL;
    echo 'Avant d\'appliquer, sauvegarder la table :' . PHP_EOL;
    echo '  mysqldump -u UTILISATEUR -p ' . $config['dbname'] . ' lbp_etats_journaliers > ~/rapports/points-avant-reouverture.sql' . PHP_EOL . PHP_EOL;
    echo 'Puis relancer la meme commande avec --appliquer.' . PHP_EOL;
    echo $ligne('=') . PHP_EOL;
    exit(0);
}

// ---------------------------------------------------------------------------
// Piste d'audit : meme chainage que AuditLogService::log(), recalcule ici pour
// ne pas charger bootstrap/app.php. Voir CorrigerDatesEncaissements.php pour
// les deux pieges : user_id doit etre NULL (cle etrangere vers users), et le
// JSON doit etre hache sous sa forme canonique MySQL.
// ---------------------------------------------------------------------------

const ACTION_REOUVERTURE = 'reouverture_point_caisse';
const GENESIS_AUDIT = 'GENESIS_LBP_SECURITY_SEED_2026';

/*
 * Forme sous laquelle la base rendra le JSON a la relecture, pour que
 * verifyChainIntegrity() retrouve l'empreinte.
 *
 * MySQL renormalise une colonne JSON a l'ecriture : il faut hacher ce qu'il
 * rendra, d'ou le CAST. MariaDB, lui, ne connait pas CAST(... AS JSON) - la
 * production tourne sous MariaDB et a refuse ce script le 14/09/2026 - et
 * stocke une colonne JSON mot pour mot : la chaine brute est alors la bonne.
 * On tente donc le CAST une seule fois, avant la transaction, et on garde la
 * chaine brute s'il est refuse.
 */
$castJsonDisponible = true;
try {
    $pdo->query("SELECT CAST('{}' AS JSON)")->fetchColumn();
} catch (Throwable) {
    $castJsonDisponible = false;
}

$canonique = static function (PDO $pdo, string $json) use ($castJsonDisponible): string {
    if (!$castJsonDisponible) {
        return $json;
    }

    $stmt = $pdo->prepare('SELECT CAST(:valeur AS JSON)');
    $stmt->execute(['valeur' => $json]);
    $resultat = $stmt->fetchColumn();

    return is_string($resultat) ? $resultat : $json;
};

$pdo->beginTransaction();

try {
    $dernierHash = $pdo->query(
        'SELECT hash_courant FROM lbp_audit_logs WHERE hash_courant IS NOT NULL ORDER BY id DESC LIMIT 1 FOR UPDATE'
    )->fetchColumn() ?: GENESIS_AUDIT;

    $inscrire = $pdo->prepare('
        INSERT INTO lbp_audit_logs (
            user_id, action, entity_type, entity_id, old_values, new_values,
            ip_address, user_agent, hash_precedent, hash_courant, created_at
        ) VALUES (
            NULL, :action, :entity_type, :entity_id, :old_values, :new_values,
            :ip, :ua, :precedent, :courant, :quand
        )
    ');

    $rouvrir = $pdo->prepare("
        UPDATE lbp_etats_journaliers
        SET statut = 'brouillon',
            date_soumission = NULL,
            solde_physique_declare = NULL,
            ecart_caisse = 0,
            explication_ecart = NULL,
            decompte_coupures_json = NULL,
            soumission_retroactive = 0,
            justification_retard = NULL
        WHERE id = :id AND statut = 'soumis'
    ");

    $nb = 0;

    foreach ($aRouvrir as $p) {
        $ancien = $p;
        unset($ancien['agence_name']);

        $ancienJson = $canonique($pdo, (string) json_encode($ancien, JSON_UNESCAPED_UNICODE));
        $nouveauJson = $canonique($pdo, (string) json_encode(['statut' => 'brouillon'], JSON_UNESCAPED_UNICODE));
        $quand = date('Y-m-d H:i:s');

        $courant = hash('sha256', sprintf(
            '%d|%s|%s|%d|%s|%s|%s|%s|%s',
            0,
            ACTION_REOUVERTURE,
            'lbp_etats_journaliers',
            (int) $p['id'],
            $ancienJson,
            $nouveauJson,
            'cli',
            $quand,
            $dernierHash
        ));

        $inscrire->execute([
            'action' => ACTION_REOUVERTURE,
            'entity_type' => 'lbp_etats_journaliers',
            'entity_id' => (int) $p['id'],
            'old_values' => $ancienJson,
            'new_values' => $nouveauJson,
            'ip' => 'cli',
            'ua' => 'RouvrirPointsCaisse.php',
            'precedent' => $dernierHash,
            'courant' => $courant,
            'quand' => $quand,
        ]);
        $dernierHash = $courant;

        $rouvrir->execute(['id' => (int) $p['id']]);
        $nb += $rouvrir->rowCount();

        echo 'Rouvert : ' . $p['date_jour'] . ' (declaration d\'origine conservee dans lbp_audit_logs)' . PHP_EOL;
    }

    $pdo->commit();

    echo PHP_EOL . $nb . ' point(s) remis en brouillon.' . PHP_EOL;
    echo 'Ils reapparaissent dans Finance > Points de caisse, prets a etre soumis' . PHP_EOL;
    echo 'avec le comptage reel.' . PHP_EOL;
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'ECHEC : ' . $e->getMessage() . PHP_EOL;
    echo "Aucune modification n'a ete conservee." . PHP_EOL;
    exit(1);
}

echo $ligne('=') . PHP_EOL;
