<?php

namespace App\Services\Shared;

use App\Helpers\Auth;
use App\Models\Database;
use PDO;

class AuditLogService
{
    /**
     * Enregistre une action sensible dans la table d'audit avec tracking avant/après.
     */
    public static function log(
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO lbp_audit_logs (
                    user_id, action, entity_type, entity_id, 
                    old_values, new_values, ip_address, created_at
                ) VALUES (
                    :user_id, :action, :entity_type, :entity_id, 
                    :old_values, :new_values, :ip_address, NOW()
                )
            ");
            
            $userId = Auth::id() ?: 0;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $oldJson = $oldValues ? json_encode($oldValues) : '';
            $newJson = $newValues ? json_encode($newValues) : '';
            $createdAt = date('Y-m-d H:i:s');

            // Récupérer le dernier hash enregistré pour le chaînage cryptographique
            $lastHashStmt = $pdo->query("SELECT hash_courant FROM lbp_audit_logs WHERE hash_courant IS NOT NULL ORDER BY id DESC LIMIT 1");
            $lastHash = $lastHashStmt ? $lastHashStmt->fetchColumn() : null;
            $hashPrecedent = $lastHash ?: 'GENESIS_LBP_SECURITY_SEED_2026';

            // Calcul du hash courant SHA-256 (blockchain-like infalsifiable)
            $rawPayload = sprintf('%d|%s|%s|%d|%s|%s|%s|%s|%s',
                $userId, $action, $entityType, $entityId,
                $oldJson, $newJson, $ipAddress, $createdAt, $hashPrecedent
            );
            $hashCourant = hash('sha256', $rawPayload);

            $stmt = $pdo->prepare("
                INSERT INTO lbp_audit_logs (
                    user_id, action, entity_type, entity_id, 
                    old_values, new_values, ip_address, hash_precedent, hash_courant, created_at
                ) VALUES (
                    :user_id, :action, :entity_type, :entity_id, 
                    :old_values, :new_values, :ip_address, :hash_precedent, :hash_courant, :created_at
                )
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $ipAddress,
                'hash_precedent' => $hashPrecedent,
                'hash_courant' => $hashCourant,
                'created_at' => $createdAt,
            ]);
        } catch (\Exception $e) {
            error_log(sprintf(
                '[AuditLogService] Échec écriture audit (action=%s, entity_type=%s, entity_id=%d): %s',
                $action,
                $entityType,
                $entityId,
                $e->getMessage()
            ));
        }
    }
}
