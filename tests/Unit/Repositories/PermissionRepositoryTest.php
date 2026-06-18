<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\PermissionRepository;
use App\Security\PermissionAction;
use Tests\Support\DatabaseTestCase;

final class PermissionRepositoryTest extends DatabaseTestCase
{
    public function test_entities_returns_only_active_entities_ordered(): void
    {
        $pdo = $this->database();
        $repository = new PermissionRepository($pdo);

        $entities = $repository->entities();

        self::assertCount(2, $entities);
        self::assertSame('users', $entities[0]->code);
        self::assertSame('rh_employees', $entities[1]->code);
    }

    public function test_replace_for_user_normalizes_rights_and_ignores_empty_permissions(): void
    {
        $pdo = $this->database();
        $repository = new PermissionRepository($pdo);

        $repository->replaceForUser(10, [
            1 => [PermissionAction::CREATE => true],
            2 => [PermissionAction::VIEW => false, PermissionAction::CREATE => false, PermissionAction::UPDATE => false, PermissionAction::DELETE => false],
        ]);

        $rows = $pdo->query('SELECT * FROM user_permissions ORDER BY entity_id')->fetchAll();

        self::assertCount(1, $rows);
        self::assertSame(1, (int) $rows[0]['entity_id']);
        self::assertSame(1, (int) $rows[0]['can_view']);
        self::assertSame(1, (int) $rows[0]['can_create']);
    }

    public function test_allows_returns_permission_for_known_action_only(): void
    {
        $pdo = $this->database();
        $repository = new PermissionRepository($pdo);

        $repository->replaceForUser(10, [1 => [PermissionAction::VIEW => true]]);

        self::assertTrue($repository->allows(10, 'users', PermissionAction::VIEW));
        self::assertFalse($repository->allows(10, 'users', PermissionAction::DELETE));
        self::assertFalse($repository->allows(10, 'users', 'export'));
    }

    public function test_permission_map_for_user_uses_entity_codes(): void
    {
        $pdo = $this->database();
        $repository = new PermissionRepository($pdo);

        $repository->replaceForUser(10, [
            1 => [PermissionAction::VIEW => true, PermissionAction::UPDATE => true],
        ]);

        $map = $repository->permissionMapForUser(10);

        self::assertArrayHasKey('users', $map);
        self::assertTrue($map['users'][PermissionAction::VIEW]);
        self::assertTrue($map['users'][PermissionAction::UPDATE]);
        self::assertFalse($map['users'][PermissionAction::CREATE]);
    }

    private function database(): \PDO
    {
        $pdo = $this->sqlite();
        $pdo->exec('CREATE TABLE permission_entities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            module TEXT NOT NULL,
            name TEXT NOT NULL,
            description TEXT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1
        )');
        $pdo->exec('CREATE TABLE user_permissions (
            user_id INTEGER NOT NULL,
            entity_id INTEGER NOT NULL,
            can_view INTEGER NOT NULL DEFAULT 0,
            can_create INTEGER NOT NULL DEFAULT 0,
            can_update INTEGER NOT NULL DEFAULT 0,
            can_delete INTEGER NOT NULL DEFAULT 0
        )');
        $pdo->exec("INSERT INTO permission_entities (id, code, module, name, sort_order, is_active) VALUES
            (2, 'rh_employees', 'Ressources humaines', 'Collaborateurs', 20, 1),
            (1, 'users', 'Administration', 'Utilisateurs', 10, 1),
            (3, 'inactive_entity', 'Archive', 'Inactive', 5, 0)");

        return $pdo;
    }
}
