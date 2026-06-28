<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class PayrollEnginePage
{
    /**
     * @param array<int,array<string,mixed>> $contractRules
     * @param array<int,array<string,mixed>> $lineItems
     * @param array<string,mixed> $payrollSettings
     * @param array<int,array<string,mixed>> $periods
     * @param array<int,array<string,mixed>> $slips
     */
    public function __construct(
        public readonly array $contractRules = [],
        public readonly array $lineItems = [],
        public readonly array $payrollSettings = [],
        public readonly array $periods = [],
        public readonly array $slips = [],
    ) {
    }
}
