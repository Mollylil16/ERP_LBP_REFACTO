<?php

namespace App\Services\Shared;

use App\Helpers\Auth;
use App\Models\Database;
use PDO;

/**
 * Service d'audit trail immuable (chaînage SHA-256 façon blockchain légère).
 *
 * Chaque entrée est liée à la précédente par un hash cryptographique : toute
 * modification a posteriori d'une ligne (même par un admin BDD direct) casse
 * la chaîne et devient détectable via verifyChainIntegrity().
 */
class AuditLogService
{
    private const GENESIS_SEED = 'GENESIS_LBP_SECURITY_SEED_2026';

    /**
     * Enregistre une action sensible dans la table d'audit avec chaînage SHA-256.
     */
    public static function log(
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $userAgent = null
    ): ?int {
        try {
            $pdo = Database::getConnection();

            $userId = Auth::id() ?: 0;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $userAgent ?? substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
            $oldJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
            $newJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
            $createdAt = date('Y-m-d H:i:s');

            // Récupérer le dernier hash pour le chaînage cryptographique
            $lastHashStmt = $pdo->query(
                "SELECT hash_courant FROM lbp_audit_logs WHERE hash_courant IS NOT NULL ORDER BY id DESC LIMIT 1"
            );
            $lastHash = $lastHashStmt ? $lastHashStmt->fetchColumn() : null;
            $hashPrecedent = $lastHash ?: self::GENESIS_SEED;

            // Calcul du hash courant SHA-256 (blockchain légère infalsifiable)
            $rawPayload = sprintf(
                '%d|%s|%s|%d|%s|%s|%s|%s|%s',
                $userId, $action, $entityType, $entityId,
                $oldJson ?? '', $newJson ?? '', $ipAddress, $createdAt, $hashPrecedent
            );
            $hashCourant = hash('sha256', $rawPayload);

            $stmt = $pdo->prepare("
                INSERT INTO lbp_audit_logs (
                    user_id, action, entity_type, entity_id, 
                    old_values, new_values, ip_address,
                    user_agent, hash_precedent, hash_courant, created_at
                ) VALUES (
                    :user_id, :action, :entity_type, :entity_id, 
                    :old_values, :new_values, :ip_address,
                    :user_agent, :hash_precedent, :hash_courant, :created_at
                )
            ");

            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldJson,
                'new_values' => $newJson,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'hash_precedent' => $hashPrecedent,
                'hash_courant' => $hashCourant,
                'created_at' => $createdAt,
            ]);

            return (int) $pdo->lastInsertId();
        } catch (\Exception $e) {
            error_log(sprintf(
                '[AuditLogService] Échec écriture audit (action=%s, entity_type=%s, entity_id=%d): %s',
                $action,
                $entityType,
                $entityId,
                $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * Enregistre un accès au module surveillance (auto-audit).
     * Utilisé par le SurveillanceAccessMiddleware pour loguer même les consultations DG.
     */
    public static function logSurveillanceAccess(string $route, bool $granted): ?int
    {
        return self::log(
            $granted ? 'surveillance_access_granted' : 'surveillance_access_denied',
            'surveillance_module',
            0,
            null,
            [
                'route' => $route,
                'granted' => $granted,
                'timestamp' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Vérifie l'intégrité de la chaîne de hash sur l'ensemble du journal d'audit.
     *
     * Parcourt toutes les entrées dans l'ordre et recalcule chaque hash pour
     * détecter toute altération a posteriori (même par un admin BDD direct).
     *
     * @return array{valid: bool, total: int, checked: int, broken: array<int, array<string, mixed>>}
     */
    public static function verifyChainIntegrity(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT id, user_id, action, entity_type, entity_id,
                   old_values, new_values, ip_address,
                   hash_precedent, hash_courant, created_at
            FROM lbp_audit_logs
            WHERE hash_courant IS NOT NULL
            ORDER BY id ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $broken = [];
        $previousHash = self::GENESIS_SEED;
        $checked = 0;

        foreach ($rows as $row) {
            $checked++;

            // Vérifier le chaînage : hash_precedent doit correspondre au hash_courant de la ligne précédente
            if ($row['hash_precedent'] !== $previousHash) {
                $broken[] = [
                    'id' => (int) $row['id'],
                    'type' => 'chain_break',
                    'expected_hash_precedent' => $previousHash,
                    'actual_hash_precedent' => $row['hash_precedent'],
                ];
            }

            // Recalculer le hash et le comparer
            $rawPayload = sprintf(
                '%d|%s|%s|%d|%s|%s|%s|%s|%s',
                (int) $row['user_id'],
                $row['action'],
                $row['entity_type'],
                (int) $row['entity_id'],
                $row['old_values'] ?? '',
                $row['new_values'] ?? '',
                $row['ip_address'] ?? '127.0.0.1',
                $row['created_at'],
                $row['hash_precedent']
            );
            $expectedHash = hash('sha256', $rawPayload);

            if ($expectedHash !== $row['hash_courant']) {
                $broken[] = [
                    'id' => (int) $row['id'],
                    'type' => 'hash_mismatch',
                    'expected_hash' => $expectedHash,
                    'actual_hash' => $row['hash_courant'],
                ];
            }

            $previousHash = $row['hash_courant'];
        }

        return [
            'valid' => empty($broken),
            'total' => count($rows),
            'checked' => $checked,
            'broken' => $broken,
        ];
    }
}
