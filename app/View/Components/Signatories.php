<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Pages\Rh\SignatoryIndexPage;

final class Signatories
{
    public static function signatoriesPage(SignatoryIndexPage $page, string $csrfToken): string
    {
        $header = Ui::pageHeader(
            'Signataires RH Habilites',
            'Definissez les collaborateurs habilites a signer les documents officiels generes par l\'ERP (contrats, ordres de mission, bulletins).',
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
            . self::signatoryForm('rh/signatories', $page->employees, $csrfToken)
            . self::signatoriesList($page->signatories, 'rh/signatories/toggle', $csrfToken, [$page, 'formatRole'])
            . '</div>';

        return '<div class="finea-shell rh-signatories-page">'
            . '<div class="finea-container">'
            . $header
            . $grid
            . '</div>'
            . '</div>';
    }
    /**
     * @param array<int,array<string,mixed>> $employees
     */
    public static function signatoryForm(string $action, array $employees, string $csrfToken): string
    {
        $optionsHtml = '<option value="">-- Selectionner un collaborateur --</option>';
        foreach ($employees as $emp) {
            $optionsHtml .= '<option value="' . (int)$emp['id'] . '">'
                . View::e((string)$emp['full_name']) . ' (' . View::e((string)($emp['employee_number'] ?: 'Sans matricule')) . ')'
                . '</option>';
        }

        $employeeSelect = '<div class="form-group" style="margin-bottom: 15px;">'
            . '<label for="employee_id" class="finea-form-label" style="display: block; font-weight: 600; margin-bottom: 5px;">Collaborateur</label>'
            . '<select name="employee_id" id="employee_id" class="finea-form-control" style="width: 100%; padding: 8px; border: 1px solid var(--finea-border); border-radius: 4px;" required>'
            . $optionsHtml
            . '</select>'
            . '</div>';

        $roleSelect = '<div class="form-group" style="margin-bottom: 15px;">'
            . '<label for="role" class="finea-form-label" style="display: block; font-weight: 600; margin-bottom: 5px;">Role de signature</label>'
            . '<select name="role" id="role" class="finea-form-control" style="width: 100%; padding: 8px; border: 1px solid var(--finea-border); border-radius: 4px;" required>'
            . '<option value="directeur_rh">Directeur des Ressources Humaines</option>'
            . '<option value="dg">Directeur General</option>'
            . '<option value="responsable_paie">Responsable de la Paie</option>'
            . '</select>'
            . '</div>';

        $titleInput = Form::input('title', [
            'label' => 'Titre / Fonction exacte sur les documents',
            'placeholder' => 'ex: Directeur des Ressources Humaines Adjoint',
            'required' => true,
            'id' => 'signatory_title',
        ]);

        $docTypes = '<div class="form-group" style="margin-bottom: 15px;">'
            . '<label class="finea-form-label" style="display: block; font-weight: 600; margin-bottom: 5px;">Types de documents autorises</label>'
            . '<div style="display: grid; grid-template-columns: 1fr; gap: 8px; margin-top: 5px;">'
            . '<label><input type="checkbox" name="document_types[]" value="contracts"> Contrats de travail</label>'
            . '<label><input type="checkbox" name="document_types[]" value="missions"> Ordres de mission</label>'
            . '<label><input type="checkbox" name="document_types[]" value="payroll"> Bulletins de paie</label>'
            . '<label><input type="checkbox" name="document_types[]" value="discipline"> Demandes d\'explications / Avertissements</label>'
            . '</div>'
            . '</div>';

        $formContent = Form::hidden('_csrf_token', $csrfToken)
            . $employeeSelect
            . $roleSelect
            . $titleInput
            . $docTypes
            . '<div style="margin-top: 15px;">'
            . Ui::button('Enregistrer le signataire', ['variant' => 'primary', 'type' => 'submit'])
            . '</div>';

        $cardHtml = Rh::card($formContent, [
            'title' => 'Ajouter un signataire',
            'eyebrow' => 'Formulaire',
        ]);

        return Rh::form($action, $cardHtml, ['class' => 'rh-settings-form']);
    }

    /**
     * @param array<int,array<string,mixed>> $signatories
     */
    public static function signatoriesList(array $signatories, string $toggleAction, string $csrfToken, callable $formatRole): string
    {
        $listHtml = '';
        if ($signatories === []) {
            $listHtml = Ui::emptyState('Aucun signataire', 'Ajoutez le premier signataire pour configurer ses habilitations.');
        } else {
            foreach ($signatories as $row) {
                $rowId = (int) ($row['id'] ?? 0);
                $isActive = (int) ($row['is_active'] ?? 0) === 1;
                $activeClass = $isActive ? '' : 'is-muted';
                $btnLabel = $isActive ? 'Actif' : 'Inactif';
                $btnVariant = $isActive ? 'success' : 'secondary';
                $formattedDocTypes = str_replace(
                    ['contracts', 'missions', 'payroll', 'discipline'],
                    ['Contrats', 'Missions', 'Paie', 'Discipline'],
                    (string)$row['document_types']
                );

                $form = '<form method="post" action="' . View::url(ltrim($toggleAction, '/')) . '">'
                    . Form::hidden('_csrf_token', $csrfToken)
                    . Form::hidden('id', $rowId)
                    . Ui::button($btnLabel, ['variant' => $btnVariant, 'type' => 'submit'])
                    . '</form>';

                $listHtml .= '<article class="rh-settings-row ' . $activeClass . '" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--finea-border); gap: 15px;">'
                    . '<div style="flex: 1;">'
                    . '<strong style="display: block; font-size: 15px; color: var(--finea-text-dark);">' . View::e((string)$row['employee_name']) . '</strong>'
                    . '<span style="font-size: 13px; color: var(--finea-text-muted); display: block; margin: 3px 0;">'
                    . '💼 <strong>' . $formatRole((string)$row['role']) . '</strong> — <em>' . View::e((string)$row['title']) . '</em>'
                    . '</span>'
                    . '<small style="display: block; font-size: 12px; color: var(--finea-blue);">'
                    . 'Habilitations: ' . View::e($formattedDocTypes)
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
            'title' => 'Signataires enregistres',
            'eyebrow' => 'Habilitations',
            'actions' => [
                Ui::badge(count($signatories) . ' actif(s)', 'neutral')
            ]
        ]);
    }
}
