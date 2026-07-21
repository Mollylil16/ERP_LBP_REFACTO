<?php

declare(strict_types=1);

namespace App\View\Pages\Logistique;

use App\Models\Logistique\LogistiqueSettings;

final class ParametresPage
{
    /**
     * @param array<int, array<string, mixed>> $sites
     */
    public function __construct(
        public readonly LogistiqueSettings $settings,
        public readonly array $sites,
        public readonly ?string $successMsg = null
    ) {}
}
