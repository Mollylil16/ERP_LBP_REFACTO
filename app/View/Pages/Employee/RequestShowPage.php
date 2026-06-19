<?php

declare(strict_types=1);

namespace App\View\Pages\Employee;

final class RequestShowPage
{
    /** @param array<string,mixed> $request */
    public function __construct(
        public readonly array $request,
        public readonly string $csrfToken,
    ) {
    }

    public function canCancel(): bool
    {
        return in_array((string) ($this->request['status'] ?? ''), ['draft', 'submitted'], true);
    }
}
