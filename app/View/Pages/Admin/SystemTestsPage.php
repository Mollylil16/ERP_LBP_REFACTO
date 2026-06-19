<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

final class SystemTestsPage
{
    public readonly string $status;
    public readonly int $score;

    /**
     * @param array<string,mixed> $summary
     * @param array<int,array<string,mixed>> $modules
     * @param array<int,array<string,mixed>> $latestRuns
     */
    public function __construct(
        public readonly string $csrfToken,
        public readonly array $summary,
        public readonly array $modules,
        public readonly array $latestRuns,
    ) {
        $this->status = (string) ($summary['healthStatus'] ?? 'warning');
        $this->score = (int) ($summary['healthScore'] ?? 0);
    }
}
