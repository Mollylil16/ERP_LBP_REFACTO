<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\Admin\UserRepository;
use Tests\Support\DatabaseTestCase;

final class UserRepositoryTest extends DatabaseTestCase
{
    private \PDO $sqlitePdo;
    private UserRepository $userRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sqlitePdo = $this->sqlite();

        $this->sqlitePdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            phone TEXT NULL,
            password_hash TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            is_admin INTEGER NOT NULL DEFAULT 0,
            rh_employee_id INTEGER NULL,
            agence_id INTEGER NULL,
            zone_regionale_id INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )");

        $this->sqlitePdo->exec("CREATE TABLE IF NOT EXISTS lbp_user_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            created_at TEXT NULL,
            UNIQUE(user_id, role)
        )");

        $this->userRepo = new UserRepository($this->sqlitePdo);
    }

    public function testCreateAndFindUserByEmail(): void
    {
        $user = new User(
            id: null,
            fullName: 'Test Admin User',
            email: 'admin.test@labelleporte.ci',
            phone: '+225 0700000000',
            passwordHash: password_hash('password123', PASSWORD_DEFAULT),
            status: 'active',
            isAdmin: true,
            roles: ['admin', 'dg']
        );

        $id = $this->userRepo->create($user);
        $this->assertGreaterThan(0, $id);

        $found = $this->userRepo->findByEmail('admin.test@labelleporte.ci');
        $this->assertNotNull($found);
        $this->assertSame('Test Admin User', $found->fullName);
        $this->assertTrue($found->isAdmin);
    }

    public function testSetAndGetRoles(): void
    {
        $user = new User(
            id: null,
            fullName: 'Test RH User',
            email: 'rh.test@labelleporte.ci',
            phone: '+225 0101010101',
            passwordHash: password_hash('password123', PASSWORD_DEFAULT),
            status: 'active',
            isAdmin: false,
            roles: ['rh', 'rh_manager']
        );

        $id = $this->userRepo->create($user);
        $this->userRepo->setRoles($id, ['rh', 'rh_manager', 'rh_responsable']);

        $roles = $this->userRepo->getRoles($id);
        $this->assertCount(3, $roles);
        $this->assertContains('rh', $roles);
        $this->assertContains('rh_manager', $roles);
        $this->assertContains('rh_responsable', $roles);
    }
}
