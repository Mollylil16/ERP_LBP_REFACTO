<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class PayrollIndexPage
{
    /**
     * @param array<int,array<string,mixed>> $periods
     * @param array<int,array<string,mixed>> $variables
     * @param array<int,array<string,mixed>> $slips
     */
    public function __construct(
        public readonly array $periods,
        public readonly int $activePeriodId,
        public readonly array $variables,
        public readonly array $slips
    ) {}

    public function getActivePeriod(): ?array
    {
        foreach ($this->periods as $period) {
            if ((int)$period['id'] === $this->activePeriodId) {
                return $period;
            }
        }
        return null;
    }

    public function formatPeriodStatus(string $status): string
    {
        $statuses = [
            'open' => 'Ouverte',
            'calculating' => 'Calculee',
            'closed' => 'Cloturee',
        ];
        return $statuses[$status] ?? ucfirst($status);
    }
}
