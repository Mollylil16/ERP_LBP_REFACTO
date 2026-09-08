<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Services\Employee\EmployeeRequestCatalog;

final class EmployeeRequestList
{
    public static function render(array $requests): string
    {
        if ($requests === []) {
            return '<div class="employee-empty-state" style="background:#ffffff; border:1px dashed #cbd5e1; border-radius:12px; padding:2.5rem 1.5rem; text-align:center;">'
                . '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 0.75rem auto; display:block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>'
                . '<strong style="display:block; font-size:1.05rem; color:#1e293b; margin-bottom:0.25rem;">Aucune demande enregistrée</strong>'
                . '<p style="color:#64748b; font-size:0.875rem; max-width:450px; margin:0 auto 1.25rem auto;">Vos prochaines démarches RH (congés, permissions, attestations) apparaîtront ici avec leur progression.</p>'
                . '<a href="' . View::url('espace-employe/demandes/nouvelle') . '" style="display:inline-flex; align-items:center; gap:6px; background:#0284c7; color:#ffffff; padding:0.6rem 1.25rem; font-size:0.85rem; font-weight:700; border-radius:8px; text-decoration:none; box-shadow:0 4px 12px rgba(2,132,199,0.25);">'
                . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'
                . 'Faire une nouvelle demande</a>'
                . '</div>';
        }
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
