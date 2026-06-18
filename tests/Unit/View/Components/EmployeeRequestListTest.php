<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\EmployeeRequestList;
use Tests\TestCase;

final class EmployeeRequestListTest extends TestCase
{
    public function test_renders_professional_request_cards(): void
    {
        $html = EmployeeRequestList::render([[
            'id' => 3, 'reference' => 'REQ-003', 'request_type' => 'lateness',
            'start_date' => '2026-07-03', 'end_date' => null, 'amount' => null,
            'current_step' => 'manager', 'status' => 'submitted',
        ]]);
        self::assertStringContainsString('Retard', $html);
        self::assertStringContainsString('REQ-003', $html);
        self::assertStringContainsString('employee-request-item', $html);
    }
}
