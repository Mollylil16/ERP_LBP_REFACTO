<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Pages\Rh\HolidayIndexPage;

final class Holidays
{
    public static function holidaysPage(HolidayIndexPage $page, string $csrfToken): string
    {
        $header = Ui::pageHeader(
            'Feries & Jours chaumes',
            'Definissez le calendrier annuel des jours feries nationaux, chomes ou specifiques a l\'entreprise.',
            [
                'eyebrow' => 'Parametres RH',
                'class' => 'rh-hero',
                'actions' => [
                    Ui::button('Retour au Dashboard', [
                        'href' => 'rh/dashboard',
                        'variant' => 'secondary',
                    ])
                ],
            ]
        );

        $grid = '<div class="rh-content-grid">'
            . self::holidayForm('rh/feries', $csrfToken)
            . self::holidaysList($page->holidays, 'rh/feries/toggle', $csrfToken, [$page, 'formatDate'])
            . '</div>';

        return '<div class="finea-shell rh-holidays-page">'
            . '<div class="finea-container">'
            . $header
            . $grid
            . '</div>'
            . '</div>';
    }
    public static function holidayForm(string $action, string $csrfToken): string
    {
        $nameInput = Form::input('name', [
            'label' => 'Nom du jour ferie',
            'placeholder' => 'ex: Fete du Travail, Noel...',
            'required' => true,
            'id' => 'holiday_name',
        ]);

        $dateInput = Form::input('holiday_date', [
            'label' => 'Date',
            'type' => 'date',
            'required' => true,
            'id' => 'holiday_date',
        ]);

        $checkboxInput = '<div class="form-group finea-checkbox-wrapper">'
            . '<label for="is_recurring">'
            . '<input type="checkbox" name="is_recurring" id="is_recurring" value="1">'
            . ' Repetitif chaque annee (sans changer l\'annee)'
            . '</label>'
            . '</div>';

        $formContent = Form::hidden('_csrf_token', $csrfToken)
            . $nameInput
            . $dateInput
            . $checkboxInput
            . '<div style="margin-top: 15px;">'
            . Ui::button('Ajouter au calendrier', ['variant' => 'primary', 'type' => 'submit'])
            . '</div>';

        $cardHtml = Rh::card($formContent, [
            'title' => 'Enregistrer un jour ferie',
            'eyebrow' => 'Formulaire',
        ]);

        return Rh::form($action, $cardHtml, ['class' => 'rh-settings-form']);
    }

    /**
     * @param array<int,array<string,mixed>> $holidays
     */
    public static function holidaysList(array $holidays, string $toggleAction, string $csrfToken, callable $formatDate): string
    {
        $listHtml = '';
        if ($holidays === []) {
            $listHtml = Ui::emptyState('Aucun jour ferie', 'Enregistrez le premier jour ferie ci-contre.');
        } else {
            foreach ($holidays as $row) {
                $rowId = (int) ($row['id'] ?? 0);
                $isActive = (int) ($row['is_active'] ?? 0) === 1;
                $activeClass = $isActive ? '' : 'is-muted';
                $btnLabel = $isActive ? 'Actif' : 'Inactif';
                $btnVariant = $isActive ? 'success' : 'secondary';
                $isRecurringStr = (int)($row['is_recurring'] ?? 0) === 1 ? ' <small style="color: var(--finea-blue); margin-left: 4px;">(Recurrent)</small>' : '';

                $form = '<form method="post" action="' . View::url(ltrim($toggleAction, '/')) . '">'
                    . Form::hidden('_csrf_token', $csrfToken)
                    . Form::hidden('id', $rowId)
                    . Ui::button($btnLabel, ['variant' => $btnVariant, 'type' => 'submit'])
                    . '</form>';

                $listHtml .= '<article class="rh-settings-row ' . $activeClass . '" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--finea-border); gap: 15px;">'
                    . '<div style="flex: 1;">'
                    . '<strong style="display: block; font-size: 15px; color: var(--finea-text-dark);">' . View::e((string)$row['name']) . '</strong>'
                    . '<span style="font-size: 13px; color: var(--finea-text-muted); display: flex; align-items: center; gap: 4px;">'
                    . '<svg class="rh-inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; flex-shrink: 0;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>'
                    . $formatDate($row['holiday_date'])
                    . $isRecurringStr
                    . '</span>'
                    . '</div>'
                    . '<div style="display: flex; gap: 8px;">'
                    . $form
                    . '</div>'
                    . '</article>';
            }
            $listHtml = '<div class="rh-settings-list" style="margin-top: 15px;">' . $listHtml . '</div>';
        }

        return Rh::card($listHtml, [
            'title' => 'Jours feries enregistres',
            'eyebrow' => 'Calendrier',
            'meta' => count($holidays) . ' jour(s)',
        ]);
    }
}
