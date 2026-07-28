<?php

declare(strict_types=1);

namespace App\View\Pages\Logistique;

use App\Models\Logistique\Rayon;

final class RayonsPage
{
    /**
     * @param array<int, Rayon> $rayons
     * @param array<int, array<string, mixed>> $sites
     */
    public function __construct(
        public readonly array $rayons,
        public readonly array $sites,
        public readonly ?string $successMsg = null,
        public readonly ?string $errorMsg = null
    ) {}
}
