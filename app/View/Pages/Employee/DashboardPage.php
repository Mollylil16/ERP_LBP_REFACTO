<?php

declare(strict_types=1);

namespace App\View\Pages\Employee;

final class DashboardPage
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly array $employee,
        public readonly array $requests,
        public readonly array $attendance,
        public readonly array $explanations,
        public readonly array $documents,
        public readonly array $stats,
        public readonly string $csrfToken,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data, string $csrfToken): self
    {
        return new self(
            is_array($data['employee'] ?? null) ? $data['employee'] : [],
            is_array($data['requests'] ?? null) ? $data['requests'] : [],
            is_array($data['attendance'] ?? null) ? $data['attendance'] : [],
            is_array($data['explanations'] ?? null) ? $data['explanations'] : [],
            is_array($data['documents'] ?? null) ? $data['documents'] : [],
            is_array($data['stats'] ?? null) ? $data['stats'] : [],
            $csrfToken,
        );
    }

    public function firstName(): string
    {
        return explode(' ', trim((string) ($this->employee['full_name'] ?? 'Collaborateur')))[0];
    }

    public function subtitle(): string
    {
        return (string) (($this->employee['function_name'] ?? '') ?: 'Fonction non renseignée')
            . ' · ' . (string) (($this->employee['service_name'] ?? '') ?: 'Service non renseigné');
    }
}
