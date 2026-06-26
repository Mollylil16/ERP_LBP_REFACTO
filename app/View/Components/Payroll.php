<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Ui;
use App\View\Pages\Rh\PayrollIndexPage;

final class Payroll
{
    public static function payrollPage(PayrollIndexPage $page, string $csrfToken): string
    {
        $activePeriod = $page->getActivePeriod();

        $header = Ui::pageHeader(
            'Gestion de la Paie',
            'Saisissez les variables mensuelles (jours travailles, primes, retenues) et lancez le calcul des bulletins de salaire.',
            [
                'eyebrow' => 'Operations de Paie',
                'class' => 'rh-hero',
                'actions' => [
                    Ui::button('Retour au Dashboard', [
                        'href' => 'rh/dashboard',
                        'variant' => 'secondary',
                    ])
                ],
            ]
        );

        $aside = self::periodSelector($page->periods, $page->activePeriodId, 'rh/paie')
            . self::openPeriodForm('rh/paie/periodes', $csrfToken);

        $main = '';
        if ($activePeriod) {
            $main .= self::variablesTable($page->variables, $activePeriod, 'rh/paie/variables', $csrfToken)
                . self::slipsTable($page->slips);
        } else {
            $main .= Ui::emptyState('Aucune periode active', 'Veuillez selectionner ou creer une periode de paie a gauche.');
        }

        return '<div class="finea-shell rh-payroll-page">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display: grid; grid-template-columns: 1fr 3fr; gap: 24px; margin-top: 20px;">'
            . '<aside style="display: grid; grid-template-columns: 1fr; gap: 20px; align-content: start;">'
            . $aside
            . '</aside>'
            . '<main style="display: grid; grid-template-columns: 1fr; gap: 24px;">'
            . $main
            . '</main>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
    /**
     * @param array<int,array<string,mixed>> $periods
     */
    public static function periodSelector(array $periods, ?int $activePeriodId, string $action): string
    {
        $optionsHtml = '<option value="">-- Selectionner --</option>';
        foreach ($periods as $p) {
            $id = (int)$p['id'];
            $selected = $id === $activePeriodId ? 'selected' : '';
            $statusStr = self::formatPeriodStatus((string)($p['status'] ?? ''));
            $optionsHtml .= '<option value="' . $id . '" ' . $selected . '>'
                . View::e((string)$p['code']) . ' (' . View::e($statusStr) . ')'
                . '</option>';
        }

        $formContent = '<div class="form-group">'
            . '<label for="period_id" class="finea-form-label" style="display: block; font-weight: 600; margin-bottom: 5px;">Mois actif</label>'
            . '<select name="period_id" id="period_id" class="finea-form-control" style="width: 100%; padding: 8px; border: 1px solid var(--finea-border); border-radius: 4px;" onchange="this.form.submit()">'
            . $optionsHtml
            . '</select>'
            . '</div>';

        $cardHtml = Rh::card($formContent, [
            'title' => 'Periodes de paie',
        ]);

        return Rh::form($action, $cardHtml, ['method' => 'get', 'class' => 'rh-settings-form']);
    }

    public static function openPeriodForm(string $action, string $csrfToken): string
    {
        $code = Form::input('code', [
            'label' => 'Code Periode',
            'placeholder' => 'ex: 2026-06',
            'required' => true,
            'id' => 'period_code',
        ]);

        $startDate = Form::input('start_date', [
            'label' => 'Date de debut',
            'type' => 'date',
            'required' => true,
            'id' => 'period_start_date',
        ]);

        $endDate = Form::input('end_date', [
            'label' => 'Date de fin',
            'type' => 'date',
            'required' => true,
            'id' => 'period_end_date',
        ]);

        $formContent = Form::hidden('_csrf_token', $csrfToken)
            . $code
            . $startDate
            . $endDate
            . '<div style="margin-top: 5px;">'
            . Ui::button('Ouvrir la periode', ['variant' => 'primary', 'type' => 'submit'])
            . '</div>';

        $cardHtml = Rh::card($formContent, [
            'title' => 'Ouvrir un mois',
        ]);

        return Rh::form($action, $cardHtml, ['class' => 'rh-settings-form']);
    }

    /**
     * @param array<int,array<string,mixed>> $variables
     * @param array<string,mixed> $activePeriod
     */
    public static function variablesTable(array $variables, array $activePeriod, string $action, string $csrfToken): string
    {
        $isClosed = ($activePeriod['status'] ?? '') === 'closed';
        $periodId = (int)($activePeriod['id'] ?? 0);
        $periodCode = View::e((string)($activePeriod['code'] ?? ''));

        $headingActions = '';
        if (!$isClosed) {
            $headingActions .= '<form method="post" action="' . View::url('rh/paie/calculer/' . $periodId) . '" style="display:inline-block; margin-right:8px;">'
                . Form::hidden('_csrf_token', $csrfToken)
                . '<button type="submit" class="finea-btn finea-btn--success" style="padding: 6px 12px; border: none; border-radius: 4px; color: white; cursor: pointer;">Calculer les bulletins</button>'
                . '</form>'
                . '<form method="post" action="' . View::url('rh/paie/cloturer/' . $periodId) . '" style="display:inline-block;">'
                . Form::hidden('_csrf_token', $csrfToken)
                . '<button type="submit" class="finea-btn finea-btn--danger" style="padding: 6px 12px; border: none; border-radius: 4px; color: white; cursor: pointer;">Cloturer le mois</button>'
                . '</form>';
        }

        $heading = '<div class="rh-section-heading" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">'
            . '<div>'
            . '<p class="rh-eyebrow">Variables de paie</p>'
            . '<h2 class="finea-section-title">Saisie des variables - ' . $periodCode . '</h2>'
            . '</div>'
            . '<div style="display: flex; gap: 8px;">'
            . $headingActions
            . '</div>'
            . '</div>';

        $closedBanner = '';
        if ($isClosed) {
            $closedBanner = '<div style="margin: 15px 0; padding: 12px; background: rgba(0,0,0,0.03); border-radius: 4px; font-size: 14px; color: var(--finea-text-muted);">'
                . '🔒 Cette periode de paie est cloturee. Les saisies et les recalculs sont verrouilles.'
                . '</div>';
        }

        $rowsHtml = '';
        foreach ($variables as $row) {
            $empId = (int) $row['employee_id'];
            $disabled = $isClosed ? 'disabled' : '';

            $rowsHtml .= '<tr style="border-bottom: 1px solid var(--finea-border);">'
                . '<td style="padding: 10px;">'
                . '<strong>' . View::e((string)$row['full_name']) . '</strong>'
                . '<small style="display: block; color: var(--finea-text-muted);">' . View::e((string)($row['employee_number'] ?: 'Sans')) . '</small>'
                . '</td>'
                . '<td style="padding: 10px;">'
                . '<input type="number" step="0.5" name="records[' . $empId . '][worked_days]" value="' . (float)$row['worked_days'] . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '>'
                . '</td>'
                . '<td style="padding: 10px;">'
                . '<input type="number" step="0.5" name="records[' . $empId . '][overtime_hours]" value="' . (float)$row['overtime_hours'] . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '>'
                . '</td>'
                . '<td style="padding: 10px;">'
                . '<input type="number" name="records[' . $empId . '][bonus]" value="' . (float)$row['bonus'] . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '>'
                . '</td>'
                . '<td style="padding: 10px;">'
                . '<input type="number" name="records[' . $empId . '][deductions]" value="' . (float)$row['deductions'] . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '>'
                . '</td>'
                . '<td style="padding: 10px;">'
                . '<input type="text" name="records[' . $empId . '][notes]" value="' . View::e((string)$row['notes']) . '" placeholder="Note..." class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '>'
                . '</td>'
                . '</tr>';
        }

        $tableHtml = '<div style="overflow-x: auto;">'
            . '<table class="finea-table" style="width: 100%; border-collapse: collapse; text-align: left;">'
            . '<thead>'
            . '<tr style="background: var(--finea-background-light); border-bottom: 2px solid var(--finea-border);">'
            . '<th style="padding: 10px;">Collaborateur</th>'
            . '<th style="padding: 10px; width: 90px;">Jours trav.</th>'
            . '<th style="padding: 10px; width: 90px;">H. Sup</th>'
            . '<th style="padding: 10px; width: 110px;">Primes</th>'
            . '<th style="padding: 10px; width: 110px;">Retenues</th>'
            . '<th style="padding: 10px;">Notes</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>'
            . $rowsHtml
            . '</tbody>'
            . '</table>'
            . '</div>';

        $submitButton = '';
        if (!$isClosed) {
            $submitButton = '<div style="margin-top: 15px; display: flex; justify-content: flex-end;">'
                . Ui::button('Enregistrer les variables', ['variant' => 'primary', 'type' => 'submit'])
                . '</div>';
        }

        $formContent = Form::hidden('_csrf_token', $csrfToken)
            . Form::hidden('period_id', (string)$periodId)
            . $tableHtml
            . $submitButton;

        $formHtml = Rh::form($action, $formContent, ['class' => 'rh-settings-form']);

        return '<section class="finea-section-card">' . $heading . $closedBanner . $formHtml . '</section>';
    }

    /**
     * @param array<int,array<string,mixed>> $slips
     */
    public static function slipsTable(array $slips): string
    {
        if ($slips === []) {
            return '';
        }

        $rowsHtml = '';
        foreach ($slips as $slip) {
            $rowsHtml .= '<tr style="border-bottom: 1px solid var(--finea-border);">'
                . '<td style="padding: 10px;">'
                . '<strong>' . View::e((string)$slip['full_name']) . '</strong>'
                . '<small style="display: block; color: var(--finea-text-muted);">' . View::e((string)($slip['employee_number'] ?: 'Sans')) . '</small>'
                . '</td>'
                . '<td style="padding: 10px;">'
                . number_format((float)$slip['base_salary'], 0, ',', ' ') . ' XOF'
                . '</td>'
                . '<td style="padding: 10px; color: var(--finea-success);">'
                . '+' . number_format((float)$slip['bonuses_total'], 0, ',', ' ') . ' XOF'
                . '</td>'
                . '<td style="padding: 10px; color: var(--finea-danger);">'
                . '-' . number_format((float)$slip['deductions_total'], 0, ',', ' ') . ' XOF'
                . '</td>'
                . '<td style="padding: 10px;">'
                . '<strong style="color: var(--finea-blue);">' . number_format((float)$slip['net_salary'], 0, ',', ' ') . ' XOF</strong>'
                . '</td>'
                . '</tr>';
        }

        $tableHtml = '<div style="margin-top: 15px; overflow-x: auto;">'
            . '<table class="finea-table" style="width: 100%; border-collapse: collapse; text-align: left;">'
            . '<thead>'
            . '<tr style="background: var(--finea-background-light); border-bottom: 2px solid var(--finea-border);">'
            . '<th style="padding: 10px;">Collaborateur</th>'
            . '<th style="padding: 10px; width: 120px;">Salaire de Base</th>'
            . '<th style="padding: 10px; width: 100px;">Primes/HS</th>'
            . '<th style="padding: 10px; width: 100px;">Retenues</th>'
            . '<th style="padding: 10px; width: 120px;">Salaire Net</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>'
            . $rowsHtml
            . '</tbody>'
            . '</table>'
            . '</div>';

        return Rh::card($tableHtml, [
            'title' => 'Bulletins de salaire generes',
            'eyebrow' => 'Calculs effectues',
        ]);
    }

    private static function formatPeriodStatus(string $status): string
    {
        $statuses = [
            'open' => 'Ouverte',
            'calculating' => 'Calculee',
            'closed' => 'Cloturee',
        ];
        return $statuses[$status] ?? ucfirst($status);
    }
}
