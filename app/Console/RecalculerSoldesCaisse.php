<?php

/**
 * Recalcul des soldes théoriques de caisse figés dans les états journaliers.
 *
 * Usage :
 *   php app/Console/RecalculerSoldesCaisse.php              (analyse seule, n'écrit rien)
 *   php app/Console/RecalculerSoldesCaisse.php --appliquer  (corrige la base)
 *   php app/Console/RecalculerSoldesCaisse.php --depuis=2026-01-01
 *   php app/Console/RecalculerSoldesCaisse.php --json
 *
 * Contexte : jusqu'à la correction du 10/09/2026, le solde théorique de caisse
 * comptait TOUS les modes de règlement, alors que la caissière ne compte que du
 * liquide. L'écart de caisse était donc mécaniquement négatif du montant encaissé
 * hors espèces, chaque jour, et remontait au directeur en signalement « TRÈS GRAVE »
 * contre des personnes nommées.
 *
 * Les journées déjà clôturées gardent cette valeur erronée : elles ne se recalculent
 * pas seules. Ce script les remet d'équerre, en ne retenant que les espèces.
 *
 * Le comptage physique déclaré n'est jamais touché : c'est une donnée constatée.
 * Seuls le solde théorique et l'écart qui en découle sont recalculés.
 *
 * Sans --appliquer, AUCUNE écriture n'est faite : le script se contente de montrer
 * ce qu'il changerait.
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
foreach ($arguments as $argument) {
    if (preg_match('/^--depuis=(\d{4}-\d{2}-\d{2})$/', $argument, $trouve)) {
        $depuis = $trouve[1];
    }
}

$pdo = Database::getConnection();
$modeSql = EtatJournalierRepository::MODE_SQL;

/*
 * Pour chaque état journalier, le liquide réellement encaissé ce jour-là dans cette
 * agence. On repart des paiements, seule source fiable : les totaux figés dans l'état
 * sont précisément ce qu'on cherche à corriger.
 */
// MODE_SQL attend l'alias `p` : la table des paiements le porte donc dans la
// sous-requête, et le résultat agrégé est joint sous le nom `liquide`.
$sql = "
    SELECT
        e.id,
        e.date_jour,
        e.agence_id,
        s.name AS agence_name,
        e.statut,
        e.solde_caisse_agence_xof AS solde_fige,
        e.solde_physique_declare,
        e.ecart_caisse AS ecart_fige,
        e.total_encaisse_xof,
        COALESCE(liquide.especes, 0) AS especes_reelles
    FROM lbp_etats_journaliers e
    LEFT JOIN company_sites s ON e.agence_id = s.id
    LEFT JOIN (
        SELECT f.agence_id,
               DATE(p.date_paiement) AS jour,
               SUM(CASE WHEN {$modeSql} IN ('especes', 'espece', 'cash') THEN p.montant ELSE 0 END) AS especes
        FROM lbp_paiements p
        INNER JOIN lbp_factures f ON p.facture_id = f.id
        WHERE p.devise = 'XOF'
        GROUP BY f.agence_id, DATE(p.date_paiement)
    ) liquide ON liquide.agence_id = e.agence_id AND liquide.jour = e.date_jour
    WHERE e.date_jour >= :depuis
    ORDER BY e.date_jour ASC, s.name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['depuis' => $depuis]);

$lignes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$aCorriger = [];
$inchanges = 0;
$ecartAvant = 0.0;
$ecartApres = 0.0;

foreach ($lignes as $ligne) {
    $soldeFige = (float) $ligne['solde_fige'];
    $especes = (float) $ligne['especes_reelles'];

    if (abs($soldeFige - $especes) < 0.01) {
        $inchanges++;
        continue;
    }

    $physique = $ligne['solde_physique_declare'];
    $nouvelEcart = $physique !== null ? round(((float) $physique) - $especes, 2) : 0.0;

    $ecartAvant += abs((float) $ligne['ecart_fige']);
    $ecartApres += abs($nouvelEcart);

    $aCorriger[] = [
        'id' => (int) $ligne['id'],
        'date' => substr((string) $ligne['date_jour'], 0, 10),
        'agence' => (string) ($ligne['agence_name'] ?? ('#' . $ligne['agence_id'])),
        'statut' => (string) $ligne['statut'],
        'solde_avant' => $soldeFige,
        'solde_apres' => $especes,
        'ecart_avant' => (float) $ligne['ecart_fige'],
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
        SET solde_caisse_agence_xof = :solde,
            ecart_caisse = :ecart,
            updated_at = NOW()
        WHERE id = :id
    ");

    $pdo->beginTransaction();
    try {
        foreach ($aCorriger as $correction) {
            $maj->execute([
                'id' => $correction['id'],
                'solde' => $correction['solde_apres'],
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
        'etats_examines' => count($lignes),
        'deja_corrects' => $inchanges,
        'a_corriger' => count($aCorriger),
        'corriges' => $corriges,
        'ecart_cumule_avant' => round($ecartAvant, 2),
        'ecart_cumule_apres' => round($ecartApres, 2),
        'details' => $aCorriger,
    ], JSON_UNESCAPED_UNICODE), PHP_EOL;
    exit(0);
}

$argent = static fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$trait = str_repeat('-', 96);

echo PHP_EOL . "RECALCUL DES SOLDES THÉORIQUES DE CAISSE" . PHP_EOL;
echo ($appliquer ? "Mode : APPLICATION — la base va être modifiée" : "Mode : ANALYSE — aucune écriture") . PHP_EOL;
echo "Période examinée : depuis le " . $depuis . PHP_EOL;
echo $trait . PHP_EOL;

echo "États journaliers examinés : " . count($lignes) . PHP_EOL;
echo "Déjà corrects              : " . $inchanges . PHP_EOL;
echo "À corriger                 : " . count($aCorriger) . PHP_EOL . PHP_EOL;

if ($aCorriger === []) {
    echo "Rien à faire : tous les soldes théoriques correspondent déjà aux espèces encaissées." . PHP_EOL . PHP_EOL;
    exit(0);
}

printf("   %-6s %-12s %-22s %16s %16s %14s" . PHP_EOL, 'ID', 'DATE', 'AGENCE', 'ÉCART AVANT', 'ÉCART APRÈS', 'STATUT');
echo $trait . PHP_EOL;

foreach (array_slice($aCorriger, 0, 60) as $c) {
    printf(
        "   %-6d %-12s %-22s %16s %16s %14s" . PHP_EOL,
        $c['id'],
        $c['date'],
        mb_strimwidth($c['agence'], 0, 22),
        $argent($c['ecart_avant']),
        $c['physique_declare'] !== null ? $argent($c['ecart_apres']) : 'non compté',
        $c['statut']
    );
}

if (count($aCorriger) > 60) {
    echo '   ... et ' . (count($aCorriger) - 60) . ' autre(s). Utilisez --json pour la liste complète.' . PHP_EOL;
}

echo $trait . PHP_EOL;
echo "Écart de caisse cumulé, en valeur absolue :" . PHP_EOL;
echo "   avant : " . $argent($ecartAvant) . PHP_EOL;
echo "   après : " . $argent($ecartApres) . PHP_EOL;
echo "   soit " . $argent($ecartAvant - $ecartApres) . " d'écarts qui n'existaient que par le défaut de calcul." . PHP_EOL . PHP_EOL;

if ($appliquer) {
    echo $corriges . " état(s) corrigé(s)." . PHP_EOL;
    echo "Les faux signalements correspondants disparaîtront de l'écran Anomalies." . PHP_EOL . PHP_EOL;
    exit(0);
}

echo "Aucune modification n'a été faite." . PHP_EOL;
echo "Pour appliquer ces corrections :" . PHP_EOL;
echo "   php app/Console/RecalculerSoldesCaisse.php --appliquer" . PHP_EOL . PHP_EOL;
echo "Sauvegardez la table au préalable :" . PHP_EOL;
echo "   mysqldump -u USER -p BASE lbp_etats_journaliers > sauvegarde_etats.sql" . PHP_EOL . PHP_EOL;
