<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class RecordList
{
    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $fields
     */
    public static function render(array $rows, array $fields, array $options = []): string
    {
        if ($rows === []) return Ui::emptyState((string) ($options['empty'] ?? 'Aucune donnée'));
        $titleKey = (string) ($options['title_key'] ?? array_key_first($fields));
        $subtitleKey = (string) ($options['subtitle_key'] ?? '');
        $statusKey = (string) ($options['status_key'] ?? 'status');
        $html = '<div class="finea-record-list">';
        foreach ($rows as $row) {
            $html .= '<article class="finea-record-item"><div class="finea-record-main"><strong>'
                . View::e((string) ($row[$titleKey] ?? 'Élément')) . '</strong>'
                . ($subtitleKey !== '' ? '<small>' . View::e((string) ($row[$subtitleKey] ?? '')) . '</small>' : '')
                . '</div><div class="finea-record-badges">';
            foreach ($fields as $key => $label) {
                if ($key === $titleKey || $key === $subtitleKey || $key === $statusKey) continue;
                $value = $row[$key] ?? null;
                if ($value === null || $value === '') continue;
                $html .= '<span class="finea-data-badge"><small>' . View::e($label) . '</small><strong>' . View::e((string) $value) . '</strong></span>';
            }
            if (isset($row[$statusKey])) $html .= Ui::badge((string) $row[$statusKey], self::tone((string) $row[$statusKey]));
            $html .= '</div></article>';
        }
        return $html . '</div>';
    }

    private static function tone(string $status): string
    {
        return match ($status) {
            'active', 'approved', 'completed', 'responded' => 'success',
            'rejected', 'cancelled', 'terminated' => 'danger',
            'pending', 'approval', 'draft', 'submitted' => 'warning',
            default => 'info',
        };
    }
}
