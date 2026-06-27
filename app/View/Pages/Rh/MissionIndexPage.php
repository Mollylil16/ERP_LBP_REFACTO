<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class MissionIndexPage
{
    /**
     * @param array<int,array<string,mixed>> $missions
     * @param array<int,array<string,mixed>> $employees
     */
    public function __construct(
        public readonly array $missions,
        public readonly array $employees
    ) {}

    public function formatStatus(string $status): string
    {
        $statuses = [
            'draft' => 'Brouillon',
            'submitted' => 'Soumis',
            'approved' => 'Approuve',
            'rejected' => 'Rejete',
            'cancelled' => 'Annule',
        ];
        return $statuses[$status] ?? ucfirst($status);
    }
}
