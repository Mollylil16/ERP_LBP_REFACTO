<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class PayrollWizardPage
{
    /**
     * @param array<int,array<string,mixed>> $employees
     * @param array<int,array<string,mixed>> $periods
     */
    public function __construct(
        public readonly array $employees = [],
        public readonly array $periods = [],
        public readonly ?int $selectedEmployeeId = null,
        public readonly ?int $selectedPeriodId = null
    ) {
    }
}
