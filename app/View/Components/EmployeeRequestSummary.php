<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Services\Employee\EmployeeRequestCatalog;

final class EmployeeRequestSummary
{
    public static function details(array $request): string
    {
        $metadata = $request['metadata'] ?? json_decode((string) ($request['metadata_json'] ?? ''), true) ?: [];
        $config = EmployeeRequestCatalog::get((string) $request['request_type']) ?? ['label' => $request['request_type']];
        $rows = ['Type' => $config['label'], 'Étape courante' => $request['current_step'] ?? '—'];
        if (!empty($request['start_date'])) $rows['Date / début'] = date('d/m/Y', strtotime($request['start_date']));
        if (!empty($request['end_date'])) $rows['Fin'] = date('d/m/Y', strtotime($request['end_date']));
        if (!empty($request['amount'])) $rows['Montant'] = number_format((float) $request['amount'], 0, ',', ' ') . ' FCFA';
        foreach ($metadata as $key => $value) {
            if ($value !== null && $value !== '') $rows[self::label($key)] = (string) $value;
        }
        $html = '<dl class="employee-details">';
        foreach ($rows as $label => $value) $html .= '<div><dt>' . View::e($label) . '</dt><dd>' . View::e($value) . '</dd></div>';
        return $html . '</dl>';
    }

    private static function label(string $key): string
    {
        return match ($key) {
            'leave_kind' => 'Nature du congé', 'handover' => 'Relais',
            'absence_kind' => 'Type d’absence', 'arrival_time' => 'Heure d’arrivée',
            'correction_kind' => 'Correction', 'check_in_time' => 'Entrée correcte',
            'check_out_time' => 'Sortie correcte', 'repayment_months' => 'Remboursement (mois)',
            'desired_payment_date' => 'Versement souhaité', 'document_kind' => 'Document',
            'delivery_format' => 'Format', 'subject' => 'Objet',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }
}
