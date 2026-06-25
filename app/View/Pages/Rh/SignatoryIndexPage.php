<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class SignatoryIndexPage
{
    /**
     * @param array<int,array<string,mixed>> $signatories
     * @param array<int,array<string,mixed>> $employees
     */
    public function __construct(
        public readonly array $signatories,
        public readonly array $employees
    ) {}

    public function formatRole(string $role): string
    {
        $roles = [
            'directeur_rh' => 'Directeur des Ressources Humaines',
            'dg' => 'Directeur General',
            'responsable_paie' => 'Responsable de la Paie',
        ];
        return $roles[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
}
