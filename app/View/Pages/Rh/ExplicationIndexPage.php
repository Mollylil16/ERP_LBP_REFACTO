<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class ExplicationIndexPage
{
    /**
     * @param array<int,array<string,mixed>> $explications
     * @param array<int,array<string,mixed>> $employees
     */
    public function __construct(
        public readonly array $explications,
        public readonly array $employees
    ) {}

    public function formatStatus(string $status): string
    {
        $statuses = [
            'pending_response' => 'En attente de reponse',
            'responded' => 'Repondu',
            'complement_requested' => 'Complement demande',
            'closed' => 'Cloture',
            'cancelled' => 'Annule',
        ];
        return $statuses[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
