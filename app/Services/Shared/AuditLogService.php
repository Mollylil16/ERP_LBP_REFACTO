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
            
            $userId = Auth::id();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $ipAddress,
            ]);
        } catch (\Exception $e) {
            // Empêche un échec de log d'interrompre une opération financière critique
        }
    }
}
