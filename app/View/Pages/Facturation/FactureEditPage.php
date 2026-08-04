<?php

declare(strict_types=1);

namespace App\View\Pages\Facturation;

final class FactureEditPage
{
    public function __construct(
        public readonly array $facture,
        public readonly bool $canModify,
        public readonly array $auditLog
    ) {}
}
