<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class ContractRulesPage
{
    /**
     * @param array<int,array<string,mixed>> $rules
     */
    public function __construct(public readonly array $rules) {}

    public function formatType(string $type): string
    {
        return strtoupper($type);
    }
}
