<?php

declare(strict_types=1);

namespace App\View\Pages\Employee;

final class RequestFormPage
{
    public function __construct(
        public readonly string $csrfToken,
        public readonly string $selectedType,
    ) {
    }
}
