<?php

declare(strict_types=1);

namespace App\View\Pages\Facturation;

final class FiltrePage
{
    public function __construct(
        public readonly int $startMonth,
        public readonly int $startYear,
        public readonly int $endMonth,
        public readonly int $endYear,
        public readonly int $selectedAgenceId,
        public readonly string $selectedTrajet,
        public readonly bool $canSeeAllAgencies,
        public readonly array $sites,
        public readonly array $trajets,
        public readonly array $results,
        public readonly array $kpis,
        public readonly array $pagination
    ) {}
}
