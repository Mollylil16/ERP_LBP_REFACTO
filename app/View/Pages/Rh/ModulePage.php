<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class ModulePage
{
    /** @param array<int,array{0:string,1:string}> $cards */
    public function __construct(
        public readonly string $title,
        public readonly string $code,
        public readonly array $cards,
    ) {}
}
