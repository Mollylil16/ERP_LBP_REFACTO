<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class ContractCard
{
    /** @param array<string,mixed> $contract */
    public static function render(array $contract): string
    {
        $id = (int) ($contract['id'] ?? 0);
        [$statusLabel, $statusTone] = self::status((string) ($contract['status'] ?? ''));
        $startDate = self::date((string) ($contract['start_date'] ?? ''));
        $endDate = !empty($contract['end_date']) ? self::date((string) $contract['end_date']) : 'Indéterminé';

        return '<article class="rh-contract-card">'
            . '<div class="rh-contract-card-head">'
            . '<div><span class="rh-person-number">' . View::e((string) (($contract['employee_number'] ?? '') ?: 'Sans matricule')) . '</span>'
            . '<h2>' . View::e((string) ($contract['employee_name'] ?? 'Collaborateur')) . '</h2></div>'
            . Ui::badge($statusLabel, $statusTone)
            . '</div>'
            . '<dl class="rh-person-details">'
            . self::detail('Type', (string) ($contract['contract_type'] ?? 'Non renseigné'))
            . self::detail('Période', $startDate . ' -> ' . $endDate)
            . self::detail('Salaire de base', number_format((float) ($contract['base_salary'] ?? 0), 0, ',', ' ') . ' FCFA')
            . self::detail('Essai', !empty($contract['trial_end_date']) ? self::date((string) $contract['trial_end_date']) : 'Non renseigné')
            . '</dl>'
            . '<div class="rh-person-actions">'
            . Ui::button('Détails', ['href' => 'rh/contrats/' . $id, 'variant' => 'primary'])
            . Ui::button('Modifier', ['href' => 'rh/contrats/' . $id . '/modifier', 'variant' => 'secondary'])
            . '</div>'
            . '</article>';
    }

    /** @return array{0:string,1:string} */
    public static function status(string $status): array
    {
        return match ($status) {
            'active' => ['En cours', 'success'],
            'terminated' => ['Terminé', 'danger'],
            'renewed' => ['Renouvelé', 'info'],
            default => [$status !== '' ? $status : 'Non renseigné', 'neutral'],
        };
    }

    public static function date(string $value): string
    {
        $timestamp = $value !== '' ? strtotime($value) : false;
        return $timestamp ? date('d/m/Y', $timestamp) : 'Non renseigné';
    }

    private static function detail(string $label, string $value): string
    {
        return '<div><dt>' . View::e($label) . '</dt><dd>' . View::e($value) . '</dd></div>';
    }
}
