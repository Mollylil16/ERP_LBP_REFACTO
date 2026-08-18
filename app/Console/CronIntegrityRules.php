<?php

/**
 * Cron nocturne d'évaluation des règles d'intégrité batch.
 *
 * Usage :
 *   php app/Console/CronIntegrityRules.php
 *   php app/Console/CronIntegrityRules.php --dry-run   (simule sans écrire)
 *   php app/Console/CronIntegrityRules.php --json       (sortie JSON)
 *
 * Évalue les règles qui nécessitent une analyse agrégée sur les données existantes
 * (impossible à faire en temps réel à chaque action métier) :
 *   1. ECART_ENCAISSEMENT_COMPTA  — paiements non comptabilisés
 *   2. ECART_PESEE_RECURRENT     — écarts de pesée récurrents par agent
 *   3. Recalcul de TOUS les scores employés
 *
 * Recommandation cron :
 *   0 2 * * * php /chemin/vers/app/Console/CronIntegrityRules.php >> /var/log/lbp_integrity.log 2>&1
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}
require_once BASE_PATH . '/bootstrap/app.php';

use App\Services\Shared\IntegrityRuleEngine;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$jsonOutput = in_array('--json', $argv ?? [], true);

$startTime = microtime(true);
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'dry_run' => $dryRun,
    'rules' => [],
    'scores_recalculated' => 0,
    'duration_seconds' => 0,
];

if (!$jsonOutput) {
    echo PHP_EOL;
    echo "╔════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║   CRON INTÉGRITÉ — ÉVALUATION DES RÈGLES BATCH           ║" . PHP_EOL;
    echo "╚════════════════════════════════════════════════════════════╝" . PHP_EOL;
    echo "  Démarrage : " . date('Y-m-d H:i:s') . PHP_EOL;
    if ($dryRun) {
        echo "  ⚠️  MODE DRY-RUN — Aucune écriture en base." . PHP_EOL;
    }
    echo PHP_EOL;
}

// ────────────────────────────────────────────────────
// 1. Écart Encaissement / Comptabilité
// ────────────────────────────────────────────────────
try {
    if (!$dryRun) {
        $count = IntegrityRuleEngine::batchEcartEncaissementCompta();
    } else {
        $count = 0; // En dry-run, on ne peut pas savoir sans écrire
    }
    $results['rules']['ECART_ENCAISSEMENT_COMPTA'] = [
        'status' => 'ok',
        'new_alerts' => $count,
    ];
    if (!$jsonOutput) {
        echo "  [1/3] ECART_ENCAISSEMENT_COMPTA : {$count} nouvelle(s) alerte(s)" . PHP_EOL;
    }
} catch (\Throwable $e) {
    $results['rules']['ECART_ENCAISSEMENT_COMPTA'] = [
        'status' => 'error',
        'message' => $e->getMessage(),
    ];
    if (!$jsonOutput) {
        echo "  [1/3] ECART_ENCAISSEMENT_COMPTA : ❌ " . $e->getMessage() . PHP_EOL;
    }
}

// ────────────────────────────────────────────────────
// 2. Écart de Pesée Récurrent
// ────────────────────────────────────────────────────
try {
    if (!$dryRun) {
        $count = IntegrityRuleEngine::batchEcartPeseeRecurrent();
    } else {
        $count = 0;
    }
    $results['rules']['ECART_PESEE_RECURRENT'] = [
        'status' => 'ok',
        'new_alerts' => $count,
    ];
    if (!$jsonOutput) {
        echo "  [2/3] ECART_PESEE_RECURRENT    : {$count} nouvelle(s) alerte(s)" . PHP_EOL;
    }
} catch (\Throwable $e) {
    $results['rules']['ECART_PESEE_RECURRENT'] = [
        'status' => 'error',
        'message' => $e->getMessage(),
    ];
    if (!$jsonOutput) {
        echo "  [2/3] ECART_PESEE_RECURRENT    : ❌ " . $e->getMessage() . PHP_EOL;
    }
}

// ────────────────────────────────────────────────────
// 3. Recalcul des scores
// ────────────────────────────────────────────────────
try {
    if (!$dryRun) {
        $scoredCount = IntegrityRuleEngine::batchRecalculateAllScores();
    } else {
        $scoredCount = 0;
    }
    $results['scores_recalculated'] = $scoredCount;
    if (!$jsonOutput) {
        echo "  [3/3] RECALCUL SCORES          : {$scoredCount} employé(s) recalculé(s)" . PHP_EOL;
    }
} catch (\Throwable $e) {
    $results['scores_error'] = $e->getMessage();
    if (!$jsonOutput) {
        echo "  [3/3] RECALCUL SCORES          : ❌ " . $e->getMessage() . PHP_EOL;
    }
}

$results['duration_seconds'] = round(microtime(true) - $startTime, 3);

if ($jsonOutput) {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} else {
    echo PHP_EOL;
    echo "  Durée totale : " . $results['duration_seconds'] . "s" . PHP_EOL;
    echo "  ✅ Terminé." . PHP_EOL;
    echo PHP_EOL;
}

exit(0);
