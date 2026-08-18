<?php

/**
 * Script CLI de vérification de l'intégrité de la chaîne de hash du journal d'audit.
 *
 * Usage :
 *   php app/Console/VerifyAuditIntegrity.php
 *   php app/Console/VerifyAuditIntegrity.php --json   (sortie JSON pour intégration cron)
 *
 * Vérifie que chaque entrée de lbp_audit_logs est chaînée cryptographiquement
 * à la précédente via SHA-256. Toute modification a posteriori (même par un
 * admin BDD) sera détectée comme une rupture de chaîne.
 */

declare(strict_types=1);

// Bootstrap minimal
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}
require_once BASE_PATH . '/bootstrap/app.php';

use App\Services\Shared\AuditLogService;

$jsonOutput = in_array('--json', $argv ?? [], true);

try {
    $result = AuditLogService::verifyChainIntegrity();
} catch (\Throwable $e) {
    if ($jsonOutput) {
        echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        fwrite(STDERR, "ERREUR: " . $e->getMessage() . PHP_EOL);
    }
    exit(2);
}

if ($jsonOutput) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($result['valid'] ? 0 : 1);
}

// Sortie lisible
echo PHP_EOL;
echo "╔════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║     VÉRIFICATION D'INTÉGRITÉ - JOURNAL D'AUDIT LBP       ║" . PHP_EOL;
echo "╚════════════════════════════════════════════════════════════╝" . PHP_EOL;
echo PHP_EOL;
echo "  Entrées avec hash  : " . $result['total'] . PHP_EOL;
echo "  Entrées vérifiées  : " . $result['checked'] . PHP_EOL;
echo "  Anomalies détectées: " . count($result['broken']) . PHP_EOL;
echo PHP_EOL;

if ($result['valid']) {
    echo "  ✅  INTÉGRITÉ CONFIRMÉE — Aucune altération détectée." . PHP_EOL;
    echo "      La chaîne de hash est intacte de l'entrée GENESIS" . PHP_EOL;
    echo "      jusqu'à la dernière entrée vérifiée." . PHP_EOL;
} else {
    echo "  🚨  ALTÉRATION DÉTECTÉE — " . count($result['broken']) . " anomalie(s) !" . PHP_EOL;
    echo PHP_EOL;

    foreach ($result['broken'] as $breach) {
        echo "  ─────────────────────────────────────────────────" . PHP_EOL;
        echo "  Entrée ID : " . $breach['id'] . PHP_EOL;
        echo "  Type      : " . ($breach['type'] === 'chain_break' ? 'Rupture de chaîne' : 'Hash altéré') . PHP_EOL;

        if ($breach['type'] === 'chain_break') {
            echo "  Attendu   : " . $breach['expected_hash_precedent'] . PHP_EOL;
            echo "  Trouvé    : " . $breach['actual_hash_precedent'] . PHP_EOL;
        } else {
            echo "  Hash calc.: " . $breach['expected_hash'] . PHP_EOL;
            echo "  Hash BDD  : " . $breach['actual_hash'] . PHP_EOL;
        }
    }
    echo PHP_EOL;
    echo "  ⚠️  ACTION REQUISE : Investiguer les entrées ci-dessus." . PHP_EOL;
    echo "     Ces lignes ont été modifiées après leur insertion initiale." . PHP_EOL;
}

echo PHP_EOL;
echo "  Date de vérification : " . date('Y-m-d H:i:s') . PHP_EOL;
echo PHP_EOL;

exit($result['valid'] ? 0 : 1);
