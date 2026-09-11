<?php

/**
 * Balayage des alertes envoyées au téléphone du directeur.
 *
 * Usage :
 *   php app/Console/CronAlertesDirection.php
 *   php app/Console/CronAlertesDirection.php --json
 *
 * Trois familles d'alertes différées :
 *   1. Signalements de fraude de degré TRÈS GRAVE non encore traités
 *   2. Décisions en attente depuis plus de 48 heures
 *   3. Agences dont le point de caisse n'est pas soumis passé 17 h
 *
 * Chaque alerte porte une clé d'événement unique par destinataire : relancer ce script
 * toutes les heures ne renvoie jamais deux fois la même. Il est donc sans risque de le
 * programmer fréquemment.
 *
 * Recommandation cron, toutes les heures entre 8 h et 20 h :
 *   0 8-20 * * * php /chemin/vers/app/Console/CronAlertesDirection.php >> /var/log/lbp_alertes.log 2>&1
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
use App\Services\Mobile\NotificationDirectionService;

$enJson = in_array('--json', $argv ?? [], true);
$debut = microtime(true);

try {
    $resultats = NotificationDirectionService::creer(Database::getConnection())->balayer();
} catch (Throwable $e) {
    $message = 'Balayage interrompu : ' . $e->getMessage();

    if ($enJson) {
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE), PHP_EOL;
    } else {
        fwrite(STDERR, '[LBP-ALERTES] ' . $message . PHP_EOL);
    }

    exit(1);
}

$total = array_sum($resultats);
$duree = round((microtime(true) - $debut) * 1000);

if ($enJson) {
    echo json_encode([
        'ok' => true,
        'alertes' => $resultats,
        'total' => $total,
        'duree_ms' => $duree,
    ], JSON_UNESCAPED_UNICODE), PHP_EOL;
    exit(0);
}

echo '[LBP-ALERTES] ' . date('Y-m-d H:i:s') . ' — ' . $total . ' alerte(s) émise(s) en ' . $duree . ' ms' . PHP_EOL;
echo '  Signalements de fraude      : ' . $resultats['fraude'] . PHP_EOL;
echo '  Décisions en souffrance     : ' . $resultats['decisions'] . PHP_EOL;
echo '  Agences non clôturées       : ' . $resultats['clotures'] . PHP_EOL;

exit(0);
