<?php

declare(strict_types=1);

namespace App\View\Pages\Finance;

final class BalanceAgeePage
{
    /**
     * @param array<string, mixed> $agingBuckets
     * @param array<int, array<string, mixed>> $clientDetails
     */
    public function __construct(
        public readonly array $agingBuckets,
        public readonly array $clientDetails,
        public readonly ?string $successMsg = null,
        public readonly ?string $errorMsg = null
    ) {}
}
