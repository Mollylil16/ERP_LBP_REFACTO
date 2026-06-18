<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Admin\UserRepository;
use Tests\Support\DatabaseTestCase;

final class UserRepositoryTest extends DatabaseTestCase
{
    public function test_find_by_identifier_matches_email_or_full_name_case_insensitively(): void
    {
        $repository = new UserRepository($this->database());

        $byEmail = $repository->findByIdentifier('ADMIN@ERP-LBP.LOCAL');
        $byName = $repository->findByIdentifier('agent transit');

        self::assertNotNull($byEmail);
        self::assertSame('Admin ERP', $byEmail->fullName);
        self::assertNotNull($byName);
        self::assertSame('agent@erp-lbp.local', $byName->email);
    }

    public function test_email_exists_supports_except_id(): void
    {
        $repository = new UserRepository($this->database());

        self::assertTrue($repository->emailExists('admin@erp-lbp.local'));
        self::assertFalse($repository->emailExists('admin@erp-lbp.local', 1));
    }

    public function test_paginate_applies_status_and_profile_filters(): void
    {
        $repository = new UserRepository($this->database());

        $result = $repository->paginate(['q' => '', 'status' => 'active', 'profile' => 'user'], 1, 15);

        self::assertSame(1, $result['total']);
        self::assertSame('Agent Transit', $result['items'][0]->fullName);
    }

    public function test_statistics_returns_expected_counts(): void
    {
        $repository = new UserRepository($this->database());

        self::assertSame([
            'total' => 3,
            'active' => 2,
            'restricted' => 1,
            'administrators' => 1,
        ], $repository->statistics());
    }

    private function database(): \PDO
    {
        $pdo = $this->sqlite();
        $pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NULL,
            password_hash TEXT NOT NULL,
            status TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0,
            rh_employee_id INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
        $hash = password_hash('secret', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (id, full_name, email, phone, password_hash, status, is_admin, rh_employee_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([1, 'Admin ERP', 'admin@erp-lbp.local', null, $hash, 'active', 1, null, '2026-06-18 00:00:00']);
        $stmt->execute([2, 'Agent Transit', 'agent@erp-lbp.local', '+22500000000', $hash, 'active', 0, 12, '2026-06-18 00:00:00']);
        $stmt->execute([3, 'Compte Bloqué', 'blocked@erp-lbp.local', null, $hash, 'blocked', 0, null, '2026-06-18 00:00:00']);

        return $pdo;
    }
}
