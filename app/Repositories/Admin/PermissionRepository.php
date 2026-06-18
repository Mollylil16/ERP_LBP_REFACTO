<?php

namespace App\Repositories\Admin;

use App\Models\PermissionEntity;
use App\Security\PermissionAction;
use PDO;

class PermissionRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return PermissionEntity[]
     */
    public function entities(): array
    {
        $rows = $this->pdo->query("
            SELECT *
            FROM permission_entities
            WHERE is_active = 1
            ORDER BY sort_order, module, name
        ")->fetchAll() ?: [];

        return array_map(
            static fn(array $row): PermissionEntity => new PermissionEntity(
                id: (int) $row['id'],
                code: (string) $row['code'],
                module: (string) $row['module'],
                name: (string) $row['name'],
                description: $row['description'] ?? null,
                sortOrder: (int) $row['sort_order'],
                isActive: (bool) $row['is_active'],
            ),
            $rows
        );
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                e.id AS entity_id,
                e.code,
                e.module,
                e.name,
                e.description,
                COALESCE(p.can_view, 0) AS can_view,
                COALESCE(p.can_create, 0) AS can_create,
                COALESCE(p.can_update, 0) AS can_update,
                COALESCE(p.can_delete, 0) AS can_delete
            FROM permission_entities e
            LEFT JOIN user_permissions p
                ON p.entity_id = e.id
                AND p.user_id = :user_id
            WHERE e.is_active = 1
            ORDER BY e.sort_order, e.module, e.name
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    public function replaceForUser(int $userId, array $permissions): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $delete = $this->pdo->prepare('DELETE FROM user_permissions WHERE user_id = :user_id');
            $delete->execute(['user_id' => $userId]);

            $insert = $this->pdo->prepare("
                INSERT INTO user_permissions (
                    user_id, entity_id, can_view, can_create, can_update, can_delete
                ) VALUES (
                    :user_id, :entity_id, :can_view, :can_create, :can_update, :can_delete
                )
            ");

            foreach ($permissions as $entityId => $rights) {
                $rights = PermissionAction::normalize(is_array($rights) ? $rights : []);
                if (!in_array(true, $rights, true)) {
                    continue;
                }

                $insert->execute([
                    'user_id' => $userId,
                    'entity_id' => (int) $entityId,
                    'can_view' => $rights[PermissionAction::VIEW] ? 1 : 0,
                    'can_create' => $rights[PermissionAction::CREATE] ? 1 : 0,
                    'can_update' => $rights[PermissionAction::UPDATE] ? 1 : 0,
                    'can_delete' => $rights[PermissionAction::DELETE] ? 1 : 0,
                ]);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function matrix(): array
    {
        $users = $this->pdo->query("
            SELECT id, full_name, email, status, is_admin
            FROM users
            ORDER BY is_admin DESC, full_name
        ")->fetchAll() ?: [];

        foreach ($users as &$user) {
            $user['permissions'] = [];
            if ((bool) $user['is_admin']) {
                continue;
            }
            foreach ($this->forUser((int) $user['id']) as $permission) {
                $user['permissions'][(string) $permission['code']] = $permission;
            }
        }
        unset($user);

        return $users;
    }

    public function grantedCount(): int
    {
        return (int) $this->pdo->query("
            SELECT COUNT(*)
            FROM user_permissions p
            INNER JOIN permission_entities e ON e.id = p.entity_id
            WHERE e.is_active = 1
              AND (p.can_view = 1 OR p.can_create = 1 OR p.can_update = 1 OR p.can_delete = 1)
        ")->fetchColumn();
    }

    public function allows(int $userId, string $entityCode, string $action): bool
    {
        $column = PermissionAction::column($action);
        if ($column === null) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT p.{$column}
            FROM user_permissions p
            INNER JOIN permission_entities e ON e.id = p.entity_id
            WHERE p.user_id = :user_id
              AND e.code = :code
              AND e.is_active = 1
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId, 'code' => $entityCode]);
        return (bool) $stmt->fetchColumn();
    }

    public function permissionMapForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.code, p.can_view, p.can_create, p.can_update, p.can_delete
            FROM user_permissions p
            INNER JOIN permission_entities e ON e.id = p.entity_id
            WHERE p.user_id = :user_id
              AND e.is_active = 1
        ");
        $stmt->execute(['user_id' => $userId]);

        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[(string) $row['code']] = [
                PermissionAction::VIEW => (bool) $row['can_view'],
                PermissionAction::CREATE => (bool) $row['can_create'],
                PermissionAction::UPDATE => (bool) $row['can_update'],
                PermissionAction::DELETE => (bool) $row['can_delete'],
            ];
        }

        return $map;
    }
}
