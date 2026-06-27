<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class ValidationIndexPage
{
    /**
     * @param array<int,array<string,mixed>> $requests
     * @param array<int,array<string,mixed>> $workflows
     */
    public function __construct(
        public readonly array $requests,
        public readonly array $workflows
    ) {}

    public function formatType(string $type): string
    {
        $types = [
            'leave' => 'Conge paye',
            'absence' => 'Absence',
            'lateness' => 'Retard',
            'salary_advance' => 'Avance sur salaire',
            'attendance_correction' => 'Correction de pointage',
            'document' => 'Demande de document',
            'other' => 'Autre demande',
        ];
        return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public function formatStep(string $step): string
    {
        $steps = [
            'manager' => 'Validation Manager',
            'rh' => 'Validation RH',
            'direction' => 'Validation Direction',
            'completed' => 'Termine',
        ];
        return $steps[$step] ?? ucfirst($step);
    }
}
