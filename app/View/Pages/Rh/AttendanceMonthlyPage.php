<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class AttendanceMonthlyPage
{
    /**
     * @param array<int,array<string,mixed>> $records
     * @param array<int,array<string,mixed>> $employees
     */
    public function __construct(
        public readonly int $employeeId,
        public readonly string $month,
        public readonly array $records,
        public readonly array $employees
    ) {}

    public function formatStatus(string $status): string
    {
        $statuses = [
            'present' => 'Present',
            'absent' => 'Absent',
            'half_day' => 'Demi-journee',
            'mission' => 'Mission',
            'conge' => 'Conge',
            'rest' => 'Repos',
        ];
        return $statuses[$status] ?? ucfirst($status);
    }
}
