<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\EmployeeRequestSummary;
use Tests\TestCase;

final class EmployeeRequestSummaryTest extends TestCase
{
    public function test_renders_only_relevant_specialized_metadata(): void
    {
        $html = EmployeeRequestSummary::details([
            'request_type' => 'salary_advance',
            'current_step' => 'rh',
            'start_date' => null,
            'end_date' => null,
            'amount' => 75000,
            'metadata' => ['repayment_months' => 3, 'desired_payment_date' => '2026-07-15'],
        ]);
        self::assertStringContainsString('75 000 FCFA', $html);
        self::assertStringContainsString('Remboursement (mois)', $html);
        self::assertStringNotContainsString('Date / début', $html);
    }
}
