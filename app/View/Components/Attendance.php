<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Attendance
{
    public static function dailyDatePicker(string $date, string $action): string
    {
        return '<form method="get" action="' . View::url(ltrim($action, '/')) . '" style="display: flex; align-items: center; gap: 10px;">'
            . '<input type="date" name="date" value="' . View::e($date) . '" class="finea-form-control" style="padding: 6px; border: 1px solid var(--finea-border); border-radius: 4px;" onchange="this.form.submit()">'
            . '</form>';
    }

    /**
     * @param array<int,array<string,mixed>> $records
     */
    public static function dailyTable(array $records): string
    {
        $rowsHtml = '';
        foreach ($records as $row) {
            $empId = (int) $row['employee_id'];
            $checkIn = $row['check_in_time'] ? substr((string)$row['check_in_time'], 0, 5) : '';
            $checkOut = $row['check_out_time'] ? substr((string)$row['check_out_time'], 0, 5) : '';
            $worked = $row['worked_hours'] !== null ? (float)$row['worked_hours'] : '';
            $overtime = $row['overtime_hours'] !== null ? (float)$row['overtime_hours'] : '0';

            $options = [
                'present' => 'Present',
                'absent' => 'Absent',
                'half_day' => 'Demi-journee',
                'mission' => 'Mission',
                'conge' => 'Conge',
                'rest' => 'Repos'
            ];

            $selectHtml = '<select name="records[' . $empId . '][attendance_status]" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;">';
            foreach ($options as $val => $lbl) {
                $selected = $row['attendance_status'] === $val ? 'selected' : '';
                $selectHtml .= '<option value="' . $val . '" ' . $selected . '>' . View::e($lbl) . '</option>';
            }
            $selectHtml .= '</select>';

            $rowsHtml .= '<tr style="border-bottom: 1px solid var(--finea-border);">'
                . '<td style="padding: 12px;">'
                . '<strong style="color: var(--finea-text-dark); display: block;">' . View::e((string)$row['full_name']) . '</strong>'
                . '<small style="color: var(--finea-text-muted);">' . View::e((string)($row['employee_number'] ?: 'Sans matricule')) . '</small>'
                . '</td>'
                . '<td style="padding: 12px;">' . $selectHtml . '</td>'
                . '<td style="padding: 12px;">'
                . '<input type="time" name="records[' . $empId . '][check_in_time]" value="' . View::e($checkIn) . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;">'
                . '</td>'
                . '<td style="padding: 12px;">'
                . '<input type="time" name="records[' . $empId . '][check_out_time]" value="' . View::e($checkOut) . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;">'
                . '</td>'
                . '<td style="padding: 12px;">'
                . '<input type="number" step="0.5" name="records[' . $empId . '][worked_hours]" value="' . View::e((string)$worked) . '" placeholder="8" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;">'
                . '</td>'
                . '<td style="padding: 12px;">'
                . '<input type="number" step="0.5" name="records[' . $empId . '][overtime_hours]" value="' . View::e((string)$overtime) . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;">'
                . '</td>'
                . '<td style="padding: 12px;">'
                . '<input type="text" name="records[' . $empId . '][notes]" value="' . View::e((string)$row['notes']) . '" placeholder="Observations eventuelles..." class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;">'
                . '</td>'
                . '</tr>';
        }

        return '<div style="overflow-x: auto;">'
            . '<table class="finea-table" style="width: 100%; border-collapse: collapse; text-align: left;">'
            . '<thead>'
            . '<tr style="background: var(--finea-background-light); border-bottom: 2px solid var(--finea-border);">'
            . '<th style="padding: 12px;">Collaborateur</th>'
            . '<th style="padding: 12px; width: 140px;">Statut de presence</th>'
            . '<th style="padding: 12px; width: 110px;">Arrivee</th>'
            . '<th style="padding: 12px; width: 110px;">Depart</th>'
            . '<th style="padding: 12px; width: 90px;">Heures</th>'
            . '<th style="padding: 12px; width: 90px;">H. Sup</th>'
            . '<th style="padding: 12px;">Notes / Observations</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>'
            . $rowsHtml
            . '</tbody>'
            . '</table>'
            . '</div>';
    }

    public static function dailyPage(\App\View\Pages\Rh\AttendanceDailyPage $page): string
    {
        $header = Ui::pageHeader(
            'Pointage Journalier',
            'Saisissez et enregistrez les presences, retards, absences et heures supplementaires pour la journee selectionnee.',
            [
                'eyebrow' => 'Temps & Activite',
                'class' => 'rh-hero',
                'actions' => [
                    Ui::button('Passer a la vue mensuelle', [
                        'href' => 'rh/pointage?vue=mensuel',
                        'variant' => 'accent',
                    ]),
                    Ui::button('Retour au Dashboard', [
                        'href' => 'rh/dashboard',
                        'variant' => 'secondary',
                    ])
                ],
            ]
        );

        $datePicker = self::dailyDatePicker($page->date, 'rh/pointage');
        $heading = '<div class="rh-section-heading" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">'
            . '<div>'
            . '<p class="rh-eyebrow">Date de travail</p>'
            . '<h2 class="finea-section-title">Feuille de pointage du jour</h2>'
            . '</div>'
            . $datePicker
            . '</div>';

        $formContent = Form::hidden('date', View::e($page->date))
            . self::dailyTable($page->records)
            . '<div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">'
            . Ui::button('Enregistrer le pointage', ['variant' => 'primary', 'type' => 'submit'])
            . '</div>';

        $formHtml = Rh::form('rh/pointage/journalier', $formContent);

        $sectionCard = '<div style="margin-top: 20px;">'
            . '<section class="finea-section-card">'
            . $heading
            . $formHtml
            . '</section>'
            . '</div>';

        return '<div class="finea-shell rh-attendance-daily-page">'
            . '<div class="finea-container">'
            . $header
            . $sectionCard
            . '</div>'
            . '</div>';
    }
}
