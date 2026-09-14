<?php

/**
 * Reprise de l'existant pour le pointage des colis.
 *
 * Usage :
 *   php app/Console/RepriseDepartsColis.php               (analyse seule)
 *   php app/Console/RepriseDepartsColis.php --appliquer
 *
 * Pourquoi
 * --------
 * Avant le pointage des départs, aucun colis n'avait jamais été marqué parti :
 * au 14/09/2026, 152 colis étaient « enregistre », dont 140 à destination de
 * Paris. Sans reprise, l'écran de réception de Paris serait vide alors que ces
 * colis sont en route ou déjà sur place. La direction a choisi que les agences
 * les pointent tous.
 *
 * Ce que fait le script
 * ---------------------
 * Un départ « de reprise » par trajet (agence d'envoi, agence d'arrivée), auquel
 * sont rattachés les colis enregistrés, pas encore partis, et destinés à une
 * autre agence que la leur. La condition est celle de l'écran « Préparer un
 * départ » : elle est lue dans PointageColisRepository, pour ne jamais diverger.
 * Chaque rattachement est tracé dans lbp_colis_pointages, source REPRISE.
 *
 * Le script ne tourne qu'une fois : s'il existe déjà un départ de reprise, il
 * refuse. Les colis sans agence d'arrivée ne peuvent être attendus nulle part :
 * ils sont listés, pour être complétés à la main dans la fiche colis.
 *
 * Avant d'appliquer :
 *   mysqldump --no-tablespaces -u UTILISATEUR -p BASE lbp_colis lbp_expeditions lbp_colis_pointages > ~/rapports/avant-reprise.sql
 *
 * Sans --appliquer, AUCUNE écriture n'est faite.
 */

declare(strict_types=1);

use App\Repositories\Colisage\PointageColisRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'execute qu'en ligne de commande.\n");
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

// Sans bootstrap/app.php : il lance le MigrationRunner, qui exécute du DDL.
// Le dépôt ne dépend que de PDO, on l'inclut directement.
$config = require BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Repositories/Colisage/PointageColisRepository.php';

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

$appliquer = in_array('--appliquer', $argv ?? [], true);
$trait = static fn (string $c = '-'): string => str_repeat($c, 78);

echo $trait('=') . PHP_EOL;
echo 'REPRISE DES COLIS EXISTANTS POUR LE POINTAGE' . PHP_EOL;
echo $trait('=') . PHP_EOL;
echo 'Base : ' . $config['dbname'] . ' sur ' . $config['host'] . PHP_EOL;
echo 'Mode : ' . ($appliquer ? '*** ECRITURE ***' : 'analyse seule, rien ne sera ecrit') . PHP_EOL;
echo $trait('=') . PHP_EOL . PHP_EOL;

// ---------------------------------------------------------------------------
// La structure doit exister : elle est ajoutée au premier chargement d'une page
// de l'ERP après la mise à jour du code.
// ---------------------------------------------------------------------------

$attendues = [
    ['lbp_expeditions', 'date_depart_effective'],
    ['lbp_expeditions', 'est_reprise'],
    ['lbp_expeditions', 'date_premiere_reception'],
    ['lbp_colis', 'date_reception'],
    ['lbp_colis', 'reception_hors_liste'],
];
$colonne = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
);
$absentes = [];
foreach ($attendues as [$table, $nom]) {
    $colonne->execute(['t' => $table, 'c' => $nom]);
    if ((int) $colonne->fetchColumn() === 0) {
        $absentes[] = $table . '.' . $nom;
    }
}
$table = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lbp_colis_pointages'"
)->fetchColumn();
if ((int) $table === 0) {
    $absentes[] = 'lbp_colis_pointages';
}

if ($absentes !== []) {
    echo 'La base n\'est pas encore a jour. Il manque : ' . implode(', ', $absentes) . PHP_EOL . PHP_EOL;
    echo 'Ouvrez une page de l\'ERP dans votre navigateur : la structure est ajoutee' . PHP_EOL;
    echo 'au premier chargement apres la mise a jour du code. Puis relancez ce script.' . PHP_EOL;
    exit(1);
}

$repo = new PointageColisRepository($pdo);

if ($repo->existeUneReprise()) {
    echo 'REFUS : une reprise a deja ete faite. Ce script ne tourne qu\'une fois.' . PHP_EOL;
    echo 'Les nouveaux colis passent par l\'ecran « Preparer un depart ».' . PHP_EOL;
    exit(0);
}

// ---------------------------------------------------------------------------
// Ce qui serait repris
// ---------------------------------------------------------------------------

$trajets = $repo->trajetsAReprendre();
$total = array_sum(array_column($trajets, 'nb'));

echo '1. COLIS A RATTACHER, PAR TRAJET' . PHP_EOL;
echo $trait() . PHP_EOL;

if ($trajets === []) {
    echo 'Aucun colis enregistre en attente de depart.' . PHP_EOL;
} else {
    foreach ($trajets as $t) {
        printf("  %-32s -> %-32s %5d colis\n", mb_substr($t['agence_depart'], 0, 32), mb_substr($t['agence_arrivee'], 0, 32), $t['nb']);
    }
    echo $trait() . PHP_EOL;
    echo '  ' . count($trajets) . ' depart(s) de reprise, ' . $total . ' colis au total.' . PHP_EOL;
}

$sansArrivee = $repo->colisSansAgenceArrivee();

echo PHP_EOL . '2. COLIS SANS AGENCE D\'ARRIVEE (non repris, a completer a la main)' . PHP_EOL;
echo $trait() . PHP_EOL;

if ($sansArrivee === []) {
    echo 'Aucun.' . PHP_EOL;
} else {
    foreach ($sansArrivee as $c) {
        printf("  %-24s %-12s enregistre le %s  depuis %s\n", $c['numero_tracking'], $c['statut'], substr((string) $c['created_at'], 0, 10), $c['agence_depart'] ?? '?');
    }
    echo '  ' . count($sansArrivee) . ' colis.' . PHP_EOL;
}

echo PHP_EOL . $trait('=') . PHP_EOL;

if (!$appliquer) {
    echo 'ANALYSE SEULE : rien n\'a ete ecrit.' . PHP_EOL . PHP_EOL;
    echo 'Avant d\'appliquer, sauvegarder les tables :' . PHP_EOL;
    echo '  mysqldump --no-tablespaces -u UTILISATEUR -p ' . $config['dbname'] . ' lbp_colis lbp_expeditions lbp_colis_pointages > ~/rapports/avant-reprise.sql' . PHP_EOL;
    echo 'Puis :' . PHP_EOL;
    echo '  php app/Console/RepriseDepartsColis.php --appliquer' . PHP_EOL;
    echo $trait('=') . PHP_EOL;
    exit(0);
}

if ($trajets === []) {
    echo 'Rien a reprendre.' . PHP_EOL;
    exit(0);
}

// ---------------------------------------------------------------------------
// Ecriture
// ---------------------------------------------------------------------------

$pdo->beginTransaction();

try {
    $rattaches = 0;

    foreach ($trajets as $t) {
        $ids = $repo->idsAExpedierEntre($t['agence_depart_id'], $t['agence_arrivee_id']);
        if ($ids === []) {
            continue;
        }

        $reference = 'REPRISE-' . date('Ymd') . '-' . $t['agence_depart_id'] . '-' . $t['agence_arrivee_id'];
        $expeditionId = $repo->creerDepart($reference, 'AÉRIEN', $t['agence_depart_id'], $t['agence_arrivee_id'], null, true);
        $nb = $repo->rattacherAuDepart($ids, $expeditionId);

        foreach ($ids as $colisId) {
            $repo->journaliser($colisId, $expeditionId, $t['agence_depart_id'], 'DEPART', 'REPRISE', null);
        }

        $rattaches += $nb;
        printf("  %-28s %-26s -> %-26s %4d colis\n", $reference, mb_substr($t['agence_depart'], 0, 26), mb_substr($t['agence_arrivee'], 0, 26), $nb);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'ECHEC : ' . $e->getMessage() . PHP_EOL;
    echo 'Aucune modification n\'a ete conservee.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . $rattaches . ' colis rattaches a leur depart de reprise.' . PHP_EOL;
echo 'Les agences d\'arrivee peuvent maintenant les pointer dans Facturation > Reception des colis.' . PHP_EOL;
echo $trait('=') . PHP_EOL;
