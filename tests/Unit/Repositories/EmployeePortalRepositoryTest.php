<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Employee\EmployeePortalRepository;
use Tests\Support\DatabaseTestCase;

final class EmployeePortalRepositoryTest extends DatabaseTestCase
{
    public function test_request_is_strictly_scoped_to_employee(): void
    {
        $pdo = $this->sqlite();
        $pdo->exec("CREATE TABLE employee_legal_requests (
            id INTEGER PRIMARY KEY, employee_id INTEGER, request_type TEXT, reference TEXT,
            start_date TEXT, end_date TEXT, amount REAL, reason TEXT, status TEXT, current_step TEXT
        )");
        $pdo->exec("CREATE TABLE employee_request_events (
            id INTEGER PRIMARY KEY, request_id INTEGER, event_type TEXT, step TEXT, status TEXT, comment TEXT, actor_user_id INTEGER, created_at TEXT
        )");
        $pdo->exec("INSERT INTO employee_legal_requests VALUES (10, 5, 'leave', 'REQ-10', NULL, NULL, NULL, 'Test', 'submitted', 'manager')");
        $repository = new EmployeePortalRepository($pdo);
        self::assertNotNull($repository->request(5, 10));
        self::assertNull($repository->request(6, 10));
    }
}
