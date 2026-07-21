<?php

declare(strict_types=1);

namespace App\View\Pages\Crm;

final class CallCenterPage
{
    /**
     * @param array<string, mixed>|null $searchResult
     * @param array<int, mixed> $rayonsOverview
     */
    public function __construct(
        public readonly ?array $searchResult,
        public readonly array $rayonsOverview,
        public readonly string $searchQuery = ''
    ) {}
}
