<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class AttendanceDailyPage
{
    /**
     * @param array<int,array<string,mixed>> $records
     */
    public function __construct(
        public readonly string $date,
        public readonly array $records
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
