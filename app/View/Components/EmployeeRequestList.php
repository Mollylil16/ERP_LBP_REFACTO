<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Services\EmployeeRequestCatalog;

final class EmployeeRequestList
{
    public static function render(array $requests): string
    {
        if ($requests === []) return Ui::emptyState('Aucune demande', 'Vos prochaines démarches apparaîtront ici avec leur progression.');
        $catalog = EmployeeRequestCatalog::all();
        $html = '<div class="employee-request-list">';
        foreach ($requests as $request) {
            $config = $catalog[$request['request_type']] ?? ['label' => $request['request_type'], 'icon' => 'RH', 'tone' => 'slate'];
            $summary = $request['amount'] !== null
                ? number_format((float) $request['amount'], 0, ',', ' ') . ' FCFA'
                : self::period($request['start_date'] ?? null, $request['end_date'] ?? null);
            $html .= '<a class="employee-request-item" href="' . View::url('espace-employe/demandes/' . (int) $request['id']) . '">'
                . '<span class="employee-request-item-icon tone-' . View::e($config['tone']) . '">' . View::e($config['icon']) . '</span>'
                . '<span class="employee-request-item-main"><small>' . View::e((string) $request['reference']) . '</small><strong>'
                . View::e($config['label']) . '</strong><em>' . View::e($summary) . '</em></span>'
                . '<span class="employee-request-progress"><small>Étape</small><strong>' . View::e((string) $request['current_step'])
                . '</strong><span class="employee-status status-' . View::e((string) $request['status']) . '">' . View::e((string) $request['status']) . '</span></span>'
                . '<span class="employee-request-arrow" aria-hidden="true">→</span></a>';
        }
        return $html . '</div>';
    }

    private static function period(?string $start, ?string $end): string
    {
        if (!$start) return 'Sans période';
        $label = date('d/m/Y', strtotime($start));
        return $end ? $label . ' → ' . date('d/m/Y', strtotime($end)) : $label;
    }
}
