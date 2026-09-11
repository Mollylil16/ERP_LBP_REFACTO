<?php

/**
 * Recalcul des points de caisse déjà soumis.
 *
 * Usage :
 *   php app/Console/RecalculerPointsCaisse.php               (analyse seule, n'écrit rien)
 *   php app/Console/RecalculerPointsCaisse.php --appliquer   (corrige la base)
 *   php app/Console/RecalculerPointsCaisse.php --depuis=2026-01-01
 *   php app/Console/RecalculerPointsCaisse.php --agence=3403
 *   php app/Console/RecalculerPointsCaisse.php --json
 *
 * Contexte
 * --------
 * Jusqu'à la correction, le nombre de colis d'un point de caisse comptait les
 * colis du jour calendaire, alors que la règle de l'entreprise veut qu'un colis
 * saisi après 15 h parte avec l'expédition du lendemain et compte donc pour le
 * jour suivant.
 *
 * Les points déjà soumis gardent la valeur figée à la soumission : elle ne se
 * recalcule pas seule. Ce script les remet d'équerre.
 *
 * Ce qui est recalculé
 * --------------------
 * Les compteurs d'activité : colis, factures, montants facturés, encaissés et
 * restant dus.
 *
 * Ce qui n'est jamais touché
 * --------------------------
 * Le comptage physique déclaré par la caissière, qui est une donnée constatée,
 * et l'explication d'écart qu'elle a écrite. L'écart de caisse est en revanche
 * recalculé, puisqu'il découle du solde théorique.
 *
 * Sans --appliquer, AUCUNE écriture n'est faite.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'exécute qu'en ligne de commande.\n");
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

require_once BASE_PATH . '/bootstrap/app.php';

use App\Models\Database;
use App\Repositories\Finance\EtatJournalierRepository;

$arguments = $argv ?? [];
$appliquer = in_array('--appliquer', $arguments, true);
$enJson = in_array('--json', $arguments, true);

$depuis = '2000-01-01';
$agenceFiltre = 0;
foreach ($arguments as $argument) {
    if (preg_match('/^--depuis=(\d{4}-\d{2}-\d{2})$/', $argument, $trouve)) {
        $depuis = $trouve[1];
    }
    if (preg_match('/^--agence=(\d+)$/', $argument, $trouve)) {
        $agenceFiltre = (int) $trouve[1];
    }
}

$pdo = Database::getConnection();
$repo = new EtatJournalierRepository($pdo);

$sql = "
    SELECT e.id, e.agence_id, e.date_jour, e.statut,
           e.nb_colis_enregistres, e.nb_factures_emises,
           e.total_facture_xof, e.total_encaisse_xof, e.total_restant_du_xof,
           e.solde_caisse_agence_xof, e.solde_physique_declare, e.ecart_caisse,
           s.name AS agence_name
    FROM lbp_etats_journaliers e
    LEFT JOIN company_sites s ON s.id = e.agence_id
    WHERE e.date_jour >= :depuis
";
$parametres = ['depuis' => $depuis];

if ($agenceFiltre > 0) {
    $sql .= ' AND e.agence_id = :agence';
    $parametres['agence'] = $agenceFiltre;
}

$sql .= ' ORDER BY e.date_jour ASC, s.name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametres);
$etats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$aCorriger = [];
$inchanges = 0;

foreach ($etats as $etat) {
    $date = substr((string) $etat['date_jour'], 0, 10);
    $reel = $repo->computeTotalsForDay((int) $etat['agence_id'], $date);

    $nouveau = [
        'nb_colis_enregistres' => (int) $reel['nb_colis'],
        'nb_factures_emises' => (int) $reel['nb_factures'],
        'total_facture_xof' => round((float) $reel['total_facture_xof'], 2),
        'total_encaisse_xof' => round((float) $reel['total_encaisse_xof'], 2),
        'total_restant_du_xof' => round((float) $reel['total_restant_du_xof'], 2),
        'solde_caisse_agence_xof' => round((float) $reel['solde_caisse_agence_xof'], 2),
    ];

    $ancien = [
        'nb_colis_enregistres' => (int) $etat['nb_colis_enregistres'],
        'nb_factures_emises' => (int) $etat['nb_factures_emises'],
        'total_facture_xof' => round((float) $etat['total_facture_xof'], 2),
        'total_encaisse_xof' => round((float) $etat['total_encaisse_xof'], 2),
        'total_restant_du_xof' => round((float) $etat['total_restant_du_xof'], 2),
        'solde_caisse_agence_xof' => round((float) $etat['solde_caisse_agence_xof'], 2),
    ];

    if ($ancien == $nouveau) {
        $inchanges++;
        continue;
    }

    // L'écart découle du solde théorique : il se recalcule, le comptage physique
    // déclaré ne bouge jamais.
    $physique = $etat['solde_physique_declare'];
    $nouvelEcart = $physique !== null
        ? round(((float) $physique) - $nouveau['solde_caisse_agence_xof'], 2)
        : 0.0;

    $aCorriger[] = [
        'id' => (int) $etat['id'],
        'date' => $date,
        'agence' => (string) ($etat['agence_name'] ?? ('#' . $etat['agence_id'])),
        'statut' => (string) $etat['statut'],
        'avant' => $ancien,
        'apres' => $nouveau,
        'ecart_avant' => round((float) $etat['ecart_caisse'], 2),
        'ecart_apres' => $nouvelEcart,
        'physique_declare' => $physique !== null ? (float) $physique : null,
    ];
}

// ---------------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------------
$corriges = 0;

if ($appliquer && $aCorriger !== []) {
    $maj = $pdo->prepare("
        UPDATE lbp_etats_journaliers
        SET nb_colis_enregistres = :colis,
            nb_factures_emises = :factures,
            total_facture_xof = :facture_xof,
            total_encaisse_xof = :encaisse_xof,
            total_restant_du_xof = :restant_xof,
            solde_caisse_agence_xof = :solde,
            ecart_caisse = :ecart,
            updated_at = NOW()
        WHERE id = :id
    ");

    $pdo->beginTransaction();
    try {
        foreach ($aCorriger as $correction) {
            $maj->execute([
                'id' => $correction['id'],
                'colis' => $correction['apres']['nb_colis_enregistres'],
                'factures' => $correction['apres']['nb_factures_emises'],
                'facture_xof' => $correction['apres']['total_facture_xof'],
                'encaisse_xof' => $correction['apres']['total_encaisse_xof'],
                'restant_xof' => $correction['apres']['total_restant_du_xof'],
                'solde' => $correction['apres']['solde_caisse_agence_xof'],
                'ecart' => $correction['ecart_apres'],
            ]);
            $corriges++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();

        $message = 'Recalcul interrompu, aucune modification appliquée : ' . $e->getMessage();
        if ($enJson) {
            echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE), PHP_EOL;
        } else {
            fwrite(STDERR, $message . PHP_EOL);
        }
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// Restitution
// ---------------------------------------------------------------------------
if ($enJson) {
    echo json_encode([
        'ok' => true,
        'mode' => $appliquer ? 'applique' : 'analyse',
        'etats_examines' => count($etats),
        'deja_corrects' => $inchanges,
        'a_corriger' => count($aCorriger),
        'corriges' => $corriges,
        'details' => $aCorriger,
    ], JSON_UNESCAPED_UNICODE), PHP_EOL;
    exit(0);
}

$trait = str_repeat('-', 104);

echo PHP_EOL . 'RECALCUL DES POINTS DE CAISSE' . PHP_EOL;
echo ($appliquer ? 'Mode : APPLICATION — la base va être modifiée' : 'Mode : ANALYSE — aucune écriture') . PHP_EOL;
echo 'Période examinée : depuis le ' . $depuis . ($agenceFiltre > 0 ? ', agence #' . $agenceFiltre : '') . PHP_EOL;
echo $trait . PHP_EOL;

echo 'Points examinés : ' . count($etats) . PHP_EOL;
echo 'Déjà corrects   : ' . $inchanges . PHP_EOL;
echo 'À corriger      : ' . count($aCorriger) . PHP_EOL . PHP_EOL;

if ($aCorriger === []) {
    echo 'Rien à faire : tous les points de caisse correspondent déjà aux données.' . PHP_EOL . PHP_EOL;
    exit(0);
}

printf("   %-6s %-12s %-20s %-10s %22s %22s" . PHP_EOL,
    'ID', 'DATE', 'AGENCE', 'STATUT', 'COLIS  (avant → après)', 'ENCAISSÉ (avant → après)');
echo $trait . PHP_EOL;

$argent = static fn(float $v): string => number_format($v, 0, ',', ' ');

foreach (array_slice($aCorriger, 0, 60) as $c) {
    printf(
        "   %-6d %-12s %-20s %-10s %22s %22s" . PHP_EOL,
        $c['id'],
        $c['date'],
        mb_strimwidth($c['agence'], 0, 20),
        $c['statut'],
        $c['avant']['nb_colis_enregistres'] . ' → ' . $c['apres']['nb_colis_enregistres'],
        $argent($c['avant']['total_encaisse_xof']) . ' → ' . $argent($c['apres']['total_encaisse_xof'])
    );
}

if (count($aCorriger) > 60) {
    echo '   ... et ' . (count($aCorriger) - 60) . ' autre(s). Utilisez --json pour la liste complète.' . PHP_EOL;
}

echo $trait . PHP_EOL;

if ($appliquer) {
    echo $corriges . ' point(s) de caisse corrigé(s).' . PHP_EOL . PHP_EOL;
    exit(0);
}

echo 'Aucune modification n\'a été faite.' . PHP_EOL;
echo 'Pour appliquer ces corrections :' . PHP_EOL;
echo '   php app/Console/RecalculerPointsCaisse.php --appliquer' . PHP_EOL . PHP_EOL;
echo 'Sauvegardez la table au préalable :' . PHP_EOL;
echo '   mysqldump -u USER -p BASE lbp_etats_journaliers > sauvegarde_points_caisse.sql' . PHP_EOL . PHP_EOL;
