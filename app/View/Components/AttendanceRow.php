<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class AttendanceRow
{
    /** @param array<string,mixed> $row */
    public static function render(array $row): string
    {
        $employeeId = (int) ($row['employee_id'] ?? $row['id'] ?? 0);
        $status = (string) ($row['status'] ?? 'present');
        $isPresent = $status === 'present';

        return '<article class="rh-attendance-row" data-attendance-row>'
            . '<div class="rh-attendance-person">'
            . '<span class="rh-person-number">' . View::e((string) (($row['employee_number'] ?? '') ?: 'Sans matricule')) . '</span>'
            . '<strong>' . View::e((string) ($row['full_name'] ?? 'Collaborateur')) . '</strong>'
            . '<small>' . View::e((string) ($row['service_name'] ?? 'Service non renseigné')) . ' - ' . View::e((string) ($row['function_name'] ?? 'Fonction non renseignée')) . '</small>'
            . '</div>'
            . Form::hidden('attendance[' . $employeeId . '][employee_id]', $employeeId)
            . Form::checkbox('attendance[' . $employeeId . '][present]', [
                'label' => 'Présent',
                'checked' => $isPresent,
                'fieldClass' => 'rh-attendance-check',
                'data-attendance-present' => true,
            ])
            . self::timeField($employeeId, 'check_in', 'Arrivée', (string) ($row['check_in'] ?? '08:00'))
            . self::timeField($employeeId, 'check_out', 'Sortie', (string) ($row['check_out'] ?? '17:00'))
            . '<div class="rh-attendance-total"><small>Total</small><strong data-attendance-total>'
            . number_format((float) ($row['total_hours'] ?? 0), 2, ',', ' ') . ' h</strong></div>'
            . '</article>';
    }

    private static function timeField(int $employeeId, string $name, string $label, string $value): string
    {
        $value = preg_match('/^\d{2}:\d{2}/', $value) ? substr($value, 0, 5) : '';

        return Form::input('attendance[' . $employeeId . '][' . $name . ']', [
            'label' => $label,
            'type' => 'time',
            'value' => $value,
            'fieldClass' => 'rh-attendance-time',
            'data-attendance-time' => true,
        ]);
    }
}
