<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

final class ModuleMaintenanceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT module_slug, is_maintenance, reason, updated_by, updated_at
             FROM module_maintenance'
        )->fetchAll() ?: [];

        $states = [];
        foreach ($rows as $row) {
            $states[(string) $row['module_slug']] = [
                'is_maintenance' => (bool) $row['is_maintenance'],
                'reason' => (string) ($row['reason'] ?? ''),
                'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }
        return $states;
    }

    /** @return array<string,mixed> */
    public function state(string $slug): array
    {
        $statement = $this->pdo->prepare(
            'SELECT is_maintenance, reason, updated_by, updated_at
             FROM module_maintenance
             WHERE module_slug = :slug
             LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return [
            'is_maintenance' => (bool) ($row['is_maintenance'] ?? false),
            'reason' => (string) ($row['reason'] ?? ''),
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function set(string $slug, bool $maintenance, string $reason, int $userId): void
    {
        $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO module_maintenance (
                module_slug, is_maintenance, reason, updated_by, updated_at
             ) VALUES (
                :slug, :maintenance, :reason, :updated_by, CURRENT_TIMESTAMP
             )
             ON CONFLICT(module_slug) DO UPDATE SET
                is_maintenance = excluded.is_maintenance,
                reason = excluded.reason,
                updated_by = excluded.updated_by,
                updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO module_maintenance (
                module_slug, is_maintenance, reason, updated_by, updated_at
             ) VALUES (
                :slug, :maintenance, :reason, :updated_by, NOW()
             )
             ON DUPLICATE KEY UPDATE
                is_maintenance = VALUES(is_maintenance),
                reason = VALUES(reason),
                updated_by = VALUES(updated_by),
                updated_at = NOW()';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'slug' => $slug,
            'maintenance' => $maintenance ? 1 : 0,
            'reason' => $maintenance ? $reason : null,
            'updated_by' => $userId,
        ]);
    }
}
