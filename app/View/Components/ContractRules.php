<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class ContractRules
{
    public static function ruleForm(string $action, string $csrfToken): string
    {
        $options = [
            ['value' => 'cdd', 'label' => 'CDD'],
            ['value' => 'cdi', 'label' => 'CDI'],
            ['value' => 'stage', 'label' => 'Stage'],
            ['value' => 'prestataire', 'label' => 'Prestataire'],
        ];

        $select = Form::select('contract_type', 'Type de contrat', $options, 'cdd', ['id' => 'contract_type', 'required' => true]);

        $trial = Form::input('trial_duration_days', [
            'label' => 'Duree de la periode d\'essai (en jours)',
            'type' => 'number',
            'value' => '90',
            'required' => true,
            'id' => 'trial_duration_days',
        ]);

        $renewals = Form::input('max_renewals', [
            'label' => 'Nombre maximal de renouvellements autorises',
            'type' => 'number',
            'value' => '1',
            'required' => true,
            'id' => 'max_renewals',
        ]);

        $alert = Form::input('alert_days_before_end', [
            'label' => 'Alerter avant echeance (en jours)',
            'type' => 'number',
            'value' => '30',
            'required' => true,
            'id' => 'alert_days_before_end',
        ]);

        $content = Form::hidden('_csrf_token', $csrfToken)
            . $select
            . $trial
            . $renewals
            . $alert
            . '<div style="margin-top: 15px;">'
            . Ui::button('Enregistrer la regle', ['variant' => 'primary', 'type' => 'submit'])
            . '</div>';

        $card = Rh::card($content, [
            'title' => 'Definir une regle',
            'eyebrow' => 'Formulaire',
            'class' => 'rh-settings-form-card',
        ]);

        return Rh::form($action, $card, ['class' => 'rh-settings-form']);
    }

    /**
     * @param array<int,array<string,mixed>> $rules
     */
    public static function rulesList(array $rules, string $toggleAction, string $csrfToken): string
    {
        $listHtml = '';
        if ($rules === []) {
            $listHtml = Ui::emptyState('Aucune regle definie', 'Configurez les parametres par defaut ci-contre.');
        } else {
            foreach ($rules as $row) {
                $rowId = (int) ($row['id'] ?? 0);
                $isActive = (int) ($row['is_active'] ?? 0) === 1;
                $activeClass = $isActive ? '' : 'is-muted';
                $btnLabel = $isActive ? 'Actif' : 'Inactif';
                $btnVariant = $isActive ? 'success' : 'secondary';

                $form = '<form method="post" action="' . View::url(ltrim($toggleAction, '/')) . '">'
                    . Form::hidden('_csrf_token', $csrfToken)
                    . Form::hidden('id', $rowId)
                    . Ui::button($btnLabel, ['variant' => $btnVariant, 'type' => 'submit'])
                    . '</form>';

                $listHtml .= '<article class="rh-settings-row ' . $activeClass . '" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--finea-border); gap: 15px;">'
                    . '<div style="flex: 1;">'
                    . '<strong style="display: block; font-size: 15px; color: var(--finea-text-dark); text-transform: uppercase;">' . View::e((string)$row['contract_type']) . '</strong>'
                    . '<span style="font-size: 13px; color: var(--finea-text-muted); display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap; margin: 3px 0;">'
                    . '<svg class="rh-inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; flex-shrink: 0;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Essai: <strong>' . (int)$row['trial_duration_days'] . ' j</strong> | '
                    . '<svg class="rh-inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; flex-shrink: 0;"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg> Renouvellements max: <strong>' . (int)$row['max_renewals'] . '</strong>'
                    . '</span>'
                    . '<small style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--finea-gold);">'
                    . '<svg class="rh-inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px; flex-shrink: 0;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>'
                    . 'Seuil d\'alerte: ' . (int)$row['alert_days_before_end'] . ' jours avant expiration'
                    . '</small>'
                    . '</div>'
                    . '<div style="display: flex; gap: 8px;">'
                    . $form
                    . '</div>'
                    . '</article>';
            }
            $listHtml = '<div class="rh-settings-list" style="margin-top: 15px;">' . $listHtml . '</div>';
        }

        return Rh::card($listHtml, [
            'title' => 'Regles applicables',
            'eyebrow' => 'Configured',
            'meta' => count($rules) . ' regle(s)',
        ]);
    }

    public static function rulesPage(\App\View\Pages\Rh\ContractRulesPage $page, string $csrfToken): string
    {
        $header = Ui::pageHeader(
            'Regles Automatiques des Contrats',
            'Definissez la duree de la periode d\'essai, le nombre maximal de renouvellements et le seuil d\'alerte d\'echeance par type de contrat.',
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

        $formCard = self::ruleForm('rh/regles-contrats', $csrfToken);
        $listCard = self::rulesList($page->rules, 'rh/regles-contrats/toggle', $csrfToken);

        $grid = '<div class="rh-content-grid">'
            . $formCard
            . $listCard
            . '</div>';

        return '<div class="finea-shell rh-contract-rules-page">'
            . '<div class="finea-container">'
            . $header
            . $grid
            . '</div>'
            . '</div>';
    }
}
