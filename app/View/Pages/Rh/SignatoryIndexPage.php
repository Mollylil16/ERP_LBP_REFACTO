<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class SignatoryIndexPage
{
    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(
        public readonly array $settings
    ) {}
}
