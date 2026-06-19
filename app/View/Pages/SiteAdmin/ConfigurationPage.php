<?php

declare(strict_types=1);

namespace App\View\Pages\SiteAdmin;

final class ConfigurationPage
{
    /**
     * @param array<string,mixed> $branding
     * @param array<int,array<string,mixed>> $slides
     * @param array<int,array<string,mixed>> $products
     */
    public function __construct(
        public readonly string $csrfToken,
        public readonly array $branding,
        public readonly array $slides,
        public readonly array $products,
    ) {
    }
}
