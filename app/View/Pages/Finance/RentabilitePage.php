<?php

declare(strict_types=1);

namespace App\View\Pages\Finance;

final class RentabilitePage
{
    /**
     * @param array<int, array<string, mixed>> $trajets
     * @param array<string, mixed> $summary
     */
    public function __construct(
        public readonly array $trajets,
        public readonly array $summary,
        public readonly ?string $successMsg = null,
        public readonly ?string $errorMsg = null
    ) {}
}
