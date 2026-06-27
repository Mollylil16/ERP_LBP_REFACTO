<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Ui;
use App\View\Pages\Rh\PayrollIndexPage;
use App\View\Pages\Rh\PayrollWizardPage;

final class Payroll
{
    // ========================================================================
    //  WIZARD PAGE — Orchestrateur principal
    // ========================================================================

    public static function wizardPage(PayrollWizardPage $page, string $csrfToken): string
    {
        $heroActions = [
            '<a href="' . View::url('rh/pointage') . '" class="finea-btn" style="background: white; color: var(--finea-primary); font-weight: 600; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg> Pointage journalier</a>',
            '<a href="' . View::url('rh/regles-contrats') . '" class="finea-btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); font-weight: 500; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg> Regles contrats</a>',
            '<a href="' . View::url('rh/paie/moteur') . '" class="finea-btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); font-weight: 500; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg> Moteur de paie CI</a>',
            '<a href="' . View::url('rh/dashboard') . '" class="finea-btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); font-weight: 500; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg> Tableau de bord RH</a>',
        ];

        $header = Ui::pageHeader(
            'Nouvelle fiche de paie',
            'L\'assistant vous guide du choix du salarie jusqu\'au bulletin final, avec un prorata automatique sur les jours pointes, une valorisation automatique des heures supplementaires et des regles distinctes par statut de presence.',
            [
                'eyebrow' => 'PAIE RH',
                'class' => 'rh-hero rh-hero-wizard',
                'actions' => [
                    '<div style="display: flex; gap: 10px; flex-wrap: wrap;">' . implode('', $heroActions) . '</div>'
                ],
            ]
        );

        $leftCard = '<div class="payroll-wizard-left-card">'
            . self::wizardStepper(1)
            . '<form method="post" action="' . View::url('rh/paie/nouveau') . '" class="rh-payroll-wizard-form" id="wizard-form">'
            . Form::hidden('_csrf_token', $csrfToken)
            . '<input type="hidden" name="base_salary" id="hidden_base_salary" value="0" />'
            . '<input type="hidden" name="bonuses_total" id="hidden_bonuses_total" value="0" />'
            . '<input type="hidden" name="deductions_total" id="hidden_deductions_total" value="0" />'
            . '<input type="hidden" name="net_salary" id="hidden_net_salary" value="0" />'
            . '<input type="hidden" name="worked_days" id="hidden_worked_days" value="30" />'
            . self::wizardStep1($page)
            . self::wizardStep2($page)
            . self::wizardStep3($page, $csrfToken)
            . self::wizardStep4($page)
            . self::wizardNavigation()
            . '</form>'
            . '</div>';

        $rightCard = self::wizardPreviewSidebar();

        return '<div class="finea-shell rh-payroll-wizard-page">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 24px;">'
            . $leftCard
            . $rightCard
            . '</div>'
            . '</div>'
            . '</div>'
            . self::wizardScript($page);
    }

    // ========================================================================
    //  STEPPER
    // ========================================================================

    public static function wizardStepper(int $activeStep = 1): string
    {
        $steps = ['1. Salarie', '2. Contrat', '3. Gains', '4. Retenues'];
        $html = '<div class="payroll-wizard-stepper" id="wizard-stepper">';
        foreach ($steps as $i => $label) {
            $step = $i + 1;
            $active = $step === $activeStep ? ' active' : '';
            $html .= '<div id="step-tab-' . $step . '" class="payroll-step-tab' . $active . '">' . $label . '</div>';
        }
        return $html . '</div>';
    }

    // ========================================================================
    //  STEP 1 — Salarié
    // ========================================================================

    public static function wizardStep1(PayrollWizardPage $page): string
    {
        $employeeOptions = '<option value="">Selectionner un collaborateur</option>';
        foreach ($page->employees as $emp) {
            $employeeOptions .= '<option value="' . (int)$emp['id'] . '">'
                . View::e($emp['full_name']) . ' (' . View::e($emp['employee_number'] ?: 'Sans matricule') . ')'
                . '</option>';
        }

        $currentMonth = date('Y-m');

        return '<div id="wizard-step-1" class="wizard-step-content">'
            . '<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px;">'
            . '  <div class="form-group">'
            . '    <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Salarie</label>'
            . '    <select id="wizard_employee_id" name="employee_id" class="finea-form-control payroll-form-input" onchange="onEmployeeChange()">'
            . $employeeOptions
            . '    </select>'
            . '  </div>'
            . '  <div class="form-group">'
            . '    <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Periode</label>'
            . '    <input type="month" id="wizard_period" name="period_month" value="' . $currentMonth . '" class="finea-form-control payroll-form-input" onchange="onPeriodChange()" />'
            . '  </div>'
            . '</div>'
            . '<div class="payroll-info-box payroll-info-box--muted">'
            . '  Selectionnez un collaborateur et une periode. L\'assistant chargera ensuite le pointage mensuel, les heures supplementaires et les regles de paie associees.'
            . '</div>'
            . '</div>';
    }

    // ========================================================================
    //  STEP 2 — Contrat
    // ========================================================================

    public static function wizardStep2(PayrollWizardPage $page): string
    {
        return '<div id="wizard-step-2" class="wizard-step-content" style="display: none;">'
            . '<p class="rh-eyebrow" style="color: var(--finea-danger); margin-bottom: 4px;">CONTRAT</p>'
            . '<h2 style="font-size: 22px; font-weight: 700; margin-bottom: 24px; color: var(--finea-text-dark);">Choisissez le type de contrat et appliquez les regles centrales</h2>'
            . self::contractTypeGrid($page->contractRules)
            . self::activeContractSection()
            . self::rulesGrid()
            . self::monthlyAdjustments()
            . '</div>';
    }

    public static function contractTypeGrid(array $rules): string
    {
        $html = '<div class="payroll-contract-grid">';
        foreach ($rules as $rule) {
            $type = View::e((string)$rule['contract_type']);
            $label = View::e((string)$rule['label']);
            $days = (int)$rule['working_days'];
            $hs = number_format((float)$rule['overtime_multiplier'], 2, ',', '');
            $prec = number_format((float)$rule['precarity_auto_rate'], 0) . '%';
            $html .= '<div class="payroll-contract-card" data-type="' . $type . '" onclick="selectContractType(this, \'' . $type . '\')">'
                . '<strong style="font-size: 16px; display: block; margin-bottom: 10px;">' . $label . '</strong>'
                . '<span style="display: block; font-size: 13px; color: var(--finea-text-muted);">' . $days . ' jours ouvres (periode)</span>'
                . '<span style="display: block; font-size: 13px; color: var(--finea-text-muted);">HS x ' . $hs . '</span>'
                . '<span style="display: block; font-size: 13px; color: var(--finea-text-muted);">Prime de precarite automatique ' . $prec . '</span>'
                . '</div>';
        }
        return $html . '</div>';
    }

    public static function activeContractSection(): string
    {
        return '<div class="payroll-active-contract" id="active-contract-section">'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">'
            . '  <div>'
            . '    <p class="rh-eyebrow" style="color: var(--finea-primary);">CONTRAT ACTIF</p>'
            . '    <h3 id="active-contract-name" style="font-size: 20px; font-weight: 700; margin-bottom: 10px;">Parametrage libre</h3>'
            . '    <p style="color: var(--finea-text-muted); font-size: 13px; line-height: 1.6; margin-bottom: 16px;">Les coefficients de paie sont desormais centralises. L\'assistant les affiche mais ne les modifie plus localement.</p>'
            . '    <a href="' . View::url('rh/regles-contrats') . '" class="finea-btn" style="background: white; border: 2px solid var(--finea-danger); color: var(--finea-danger); padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">'
            . '      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
            . '      Parametrer'
            . '    </a>'
            . '    <div style="margin-top: 16px;">'
            . '      <label style="font-weight: 600; display: block; margin-bottom: 6px;">Contrat</label>'
            . '      <select id="active-contract-select" class="finea-form-control payroll-form-input" onchange="onContractSelectChange()">'
            . '        <option value="libre">Parametrage libre</option>'
            . '      </select>'
            . '    </div>'
            . '    <div class="payroll-info-box payroll-info-box--danger" style="margin-top: 12px;">'
            . '      La paie du mois utilisera exactement les coefficients affiches a droite. Pour les modifier, passez par l\'ecran de parametrage RH.'
            . '    </div>'
            . '  </div>'
            . '  <div>'
            . '    <p class="rh-eyebrow" style="color: var(--finea-text-muted);">REGLES ACTIVES</p>'
            . '    <div class="payroll-rules-grid" id="rules-grid"></div>'
            . '  </div>'
            . '</div>'
            . '</div>';
    }

    public static function rulesGrid(): string
    {
        // Placeholder — filled by JavaScript based on selected contract type
        return '';
    }

    public static function monthlyAdjustments(): string
    {
        return '<div class="payroll-adjustments-section">'
            . '<p class="rh-eyebrow" style="color: var(--finea-primary);">AJUSTEMENTS DU MOIS</p>'
            . '<h3 style="font-size: 20px; font-weight: 700; margin-bottom: 8px;">Ajustements exceptionnels du mois</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 13px; margin-bottom: 20px;">Prime exceptionnelle, retenue, acompte, rappel ou regularisation. Seuls les ajustements valides entrent dans le bulletin.</p>'
            . '<div style="display: grid; grid-template-columns: 2fr 1.5fr 1fr auto; gap: 12px; align-items: end;">'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Libelle</label>'
            . '    <input type="text" id="adj_label" placeholder="Ex: Prime exceptionnelle" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Nature</label>'
            . '    <select id="adj_nature" class="finea-form-control payroll-form-input">'
            . '      <option value="gain_exceptionnel">Gain exceptionnel</option>'
            . '      <option value="retenue_exceptionnelle">Retenue exceptionnelle</option>'
            . '      <option value="prime">Prime</option>'
            . '      <option value="regularisation">Regularisation</option>'
            . '    </select>'
            . '  </div>'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Montant</label>'
            . '    <input type="number" id="adj_amount" value="0" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '  <button type="button" onclick="addAdjustment()" style="background: var(--finea-primary); color: white; border: none; padding: 12px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">+ Ajouter</button>'
            . '</div>'
            . '<div id="adjustments-list" style="margin-top: 16px;"></div>'
            . '<input type="hidden" name="adjustments_json" id="adjustments_json" value="[]" />'
            . '</div>';
    }

    // ========================================================================
    //  STEP 3 — Gains
    // ========================================================================

    public static function wizardStep3(PayrollWizardPage $page, string $csrfToken): string
    {
        return '<div id="wizard-step-3" class="wizard-step-content" style="display: none;">'
            . '<p class="rh-eyebrow" style="color: #10b981; margin-bottom: 4px;">GAINS</p>'
            . '<h2 style="font-size: 22px; font-weight: 700; margin-bottom: 8px; color: var(--finea-text-dark);">Remuneration contractuelle</h2>'
            . '<p style="color: var(--finea-text-muted); font-size: 14px; margin-bottom: 20px;">Les montants renseignes ici constituent la remuneration contractuelle du salaire. Ils seront automatiquement repris dans les bulletins de paie de la periode concernee.</p>'
            . self::contractAlert()
            . self::contractForm($csrfToken)
            . self::remunerationDetails()
            . self::lineItemsGrid($page->lineItems)
            . '</div>';
    }

    public static function contractAlert(): string
    {
        return '<div class="payroll-alert-banner" id="contract-alert" style="display: none;">'
            . 'Aucun contrat actif trouve pour cet employe sur la periode. Le bulletin ne peut pas etre genere sans contrat RH actif.'
            . '</div>';
    }

    public static function contractForm(string $csrfToken): string
    {
        $currentDate = date('d/m/Y');
        return '<div class="payroll-contract-form-section" id="contract-form-section">'
            . '<h3 style="font-size: 20px; font-weight: 700; margin-bottom: 8px;">Creer le contrat RH</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 13px; margin-bottom: 20px;">Le salaire et les avantages recurrents doivent etre poses ici avant le calcul du bulletin.</p>'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Type de contrat</label>'
            . '    <select id="contract_form_type" name="contract_type" class="finea-form-control payroll-form-input">'
            . '      <option value="libre">Parametrage libre</option>'
            . '      <option value="cdd">CDD</option>'
            . '      <option value="cdi_permanent">CDI permanent</option>'
            . '      <option value="stage">Stage de perfectionnement</option>'
            . '      <option value="vacataire">Vacataire</option>'
            . '    </select>'
            . '  </div>'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Debut</label>'
            . '    <input type="date" id="contract_start" name="contract_start_date" value="' . date('Y-m-d') . '" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Fin</label>'
            . '    <input type="date" id="contract_end" name="contract_end_date" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '</div>'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Salaire categoriel</label>'
            . '    <input type="number" id="contract_base_salary" name="base_salary" value="0" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Sursalaire</label>'
            . '    <input type="number" id="contract_sursalaire" name="sursalaire" value="0" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Localite transport</label>'
            . '    <input type="text" id="contract_transport" name="transport_locality" placeholder="EX: ABIDJAN" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '</div>'
            . '<button type="button" onclick="saveContract()" style="background: #10b981; color: white; border: none; padding: 12px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">'
            . '  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>'
            . '  Enregistrer le contrat RH'
            . '</button>'
            . '</div>';
    }

    public static function remunerationDetails(): string
    {
        $leftFields = [
            ['Categorie', 'rem_category', 'M2'],
            ['Salaire categoriel', 'rem_salary', '0'],
            ['Sursalaire', 'rem_sursalaire', '0'],
            ['Prime anciennete', 'rem_seniority', '0'],
            ['Autres primes', 'rem_other', '0'],
            ['Gratification', 'rem_gratification', '0'],
            ['Prime conges payes', 'rem_leave', '0'],
            ['Precarite', 'rem_precarity', '0'],
        ];

        $leftHtml = '<div class="payroll-remuneration-left">';
        for ($i = 0; $i < count($leftFields); $i += 2) {
            $leftHtml .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">';
            for ($j = $i; $j < min($i + 2, count($leftFields)); $j++) {
                $f = $leftFields[$j];
                $leftHtml .= '<div>'
                    . '<label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">' . $f[0] . '</label>'
                    . '<input type="text" id="' . $f[1] . '" value="' . $f[2] . '" class="finea-form-control payroll-form-input" readonly />'
                    . '</div>';
            }
            $leftHtml .= '</div>';
        }
        $leftHtml .= '</div>';

        $rightHtml = '<div class="payroll-remuneration-right">'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">Jours payes calcules</label>'
            . '    <input type="text" id="rem_paid_days" value="0,00" class="finea-form-control payroll-form-input" readonly />'
            . '  </div>'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">Heures supplementaires du mois</label>'
            . '    <input type="number" id="rem_overtime" name="overtime_hours" value="0" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '</div>'
            . '<div class="payroll-info-box payroll-info-box--success">'
            . '  Le pointage alimente automatiquement le prorata de salaire et les heures supplementaires. Vous pouvez ajuster les heures supplementaires ici si un correctif RH est necessaire avant edition du bulletin.'
            . '</div>'
            . '</div>';

        return '<div class="payroll-remuneration-grid" id="remuneration-section" style="margin-top: 24px;">'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">'
            . $leftHtml
            . $rightHtml
            . '</div>'
            . '</div>';
    }

    /** @param array<int,array<string,mixed>> $lineItems */
    public static function lineItemsGrid(array $lineItems): string
    {
        $natureLabels = [
            'allocation_prime' => 'Allocation / prime',
            'avantage_nature' => 'Avantage en nature',
            'gain' => 'Gain',
        ];

        $html = '<div class="payroll-line-items-section" style="margin-top: 30px;">'
            . '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">'
            . '  <div>'
            . '    <p class="rh-eyebrow" style="color: #10b981;">RUBRIQUES AJOUTEES</p>'
            . '    <h3 style="font-size: 20px; font-weight: 700; margin-bottom: 8px;">Primes, indemnites et avantages recurrentiels</h3>'
            . '    <p style="color: var(--finea-text-muted); font-size: 13px;">Ces montants viennent du contrat RH du salarie. Ils ne sont pas une saisie ponctuelle de bulletin : modifiez-les dans la remuneration contractuelle.</p>'
            . '  </div>'
            . '  <a href="' . View::url('rh/personnel') . '" style="background: white; border: 2px solid #10b981; color: #10b981; padding: 10px 16px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13px; white-space: nowrap;">'
            . '    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v8M8 12h8"></path></svg>'
            . '    Gerer le contrat RH'
            . '  </a>'
            . '</div>'
            . '<div class="payroll-line-items-grid">';

        foreach ($lineItems as $item) {
            $html .= self::lineItemCard($item, $natureLabels);
        }

        return $html . '</div></div>';
    }

    /** @param array<string,mixed> $item */
    public static function lineItemCard(array $item, array $natureLabels): string
    {
        $name = View::e((string)$item['name']);
        $nature = $natureLabels[$item['nature'] ?? ''] ?? 'Autre';
        $code = View::e((string)$item['code']);

        return '<div class="payroll-line-item-card" data-code="' . $code . '">'
            . '<strong style="display: block; font-size: 14px; margin-bottom: 4px; color: var(--finea-text-dark);">' . $name . '</strong>'
            . '<span style="display: block; font-size: 12px; color: var(--finea-text-muted); margin-bottom: 10px;">' . $nature . '</span>'
            . '<input type="text" class="finea-form-control payroll-form-input line-item-amount" data-code="' . $code . '" placeholder="Montant contractuel" readonly style="background: #f8fafc; font-size: 13px;" />'
            . '</div>';
    }

    // ========================================================================
    //  STEP 4 — Retenues
    // ========================================================================

    public static function wizardStep4(PayrollWizardPage $page): string
    {
        return '<div id="wizard-step-4" class="wizard-step-content" style="display: none;">'
            . '<p class="rh-eyebrow" style="color: var(--finea-text-muted); margin-bottom: 4px;">RETENUES</p>'
            . '<h2 style="font-size: 22px; font-weight: 700; margin-bottom: 24px; color: var(--finea-text-dark);">Charges, retenues et validation finale</h2>'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">'
            . self::fiscalParameters($page->payrollSettings)
            . self::deductionsPanel()
            . '</div>'
            . '</div>';
    }

    /** @param array<string,mixed> $settings */
    public static function fiscalParameters(array $settings): string
    {
        $fields = [
            ['is_salarial_rate', 'Taux IS salarial (%)', 'Impot sur Salaire preleve sur le salaire brut imposable'],
            ['cnps_salarial_rate', 'CNPS salariale (%)', 'Part salariale de la cotisation CNPS'],
            ['cnps_patronal_rate', 'CNPS patronale (%)', 'Part patronale, non prelevee sur le salarie'],
            ['family_benefits_rate', 'Prestations familiales employeur (%)', 'Cotisation patronale prestations familiales'],
            ['work_accident_rate', 'Accident travail (%)', 'Cotisation patronale accident de travail'],
            ['apprentice_tax_rate', 'Taxe apprentissage (%)', 'Taxe pour la formation professionnelle'],
            ['professional_training_rate', 'Formation professionnelle (%)', 'Contribution formation continue'],
            ['fdfp_rate', 'FDFP (%)', 'Fonds de Developpement de la Formation Professionnelle'],
        ];

        $html = '<div class="payroll-fiscal-section">'
            . '<h3 style="font-size: 16px; font-weight: 700; margin-bottom: 6px;">Parametres sociaux et fiscaux</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 13px; margin-bottom: 20px;">Ces valeurs sont proposees par le parametrage de paie et peuvent etre controlees avant calcul.</p>';

        for ($i = 0; $i < count($fields); $i += 2) {
            $html .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">';
            for ($j = $i; $j < min($i + 2, count($fields)); $j++) {
                $f = $fields[$j];
                $val = number_format((float)($settings[$f[0]] ?? 0), 2, ',', '');
                $html .= '<div>'
                    . '<label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">' . $f[1]
                    . ' <span title="' . View::e($f[2]) . '" style="cursor: help; color: var(--finea-text-muted); font-size: 12px;">?</span>'
                    . '</label>'
                    . '<input type="number" step="0.01" name="fiscal_' . $f[0] . '" value="' . $val . '" class="finea-form-control payroll-form-input" />'
                    . '</div>';
            }
            $html .= '</div>';
        }

        // Parts fiscales + IGR manuel
        $html .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">'
            . '<div>'
            . '  <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">Parts fiscales <span title="Nombre de parts pour le calcul de l\'IGR" style="cursor: help; color: var(--finea-text-muted); font-size: 12px;">?</span></label>'
            . '  <input type="number" name="fiscal_parts" id="fiscal_parts" value="1" class="finea-form-control payroll-form-input" />'
            . '</div>'
            . '<div>'
            . '  <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">IGR manuel <span title="Saisie manuelle de l\'Impot General sur le Revenu" style="cursor: help; color: var(--finea-text-muted); font-size: 12px;">?</span></label>'
            . '  <input type="number" name="igr_manual" id="igr_manual" value="0" class="finea-form-control payroll-form-input" />'
            . '</div>'
            . '</div>';

        return $html . '</div>';
    }

    public static function deductionsPanel(): string
    {
        return '<div class="payroll-deductions-section">'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">Prime transport</label>'
            . '    <input type="text" id="ded_transport" name="transport_premium" value="Reprise du contrat" class="finea-form-control payroll-form-input" readonly style="background: #f8fafc;" />'
            . '  </div>'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">Assurance maladie</label>'
            . '    <input type="number" id="ded_health" name="health_insurance" value="1000" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '</div>'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">Avance</label>'
            . '    <input type="number" id="ded_advance" name="advance_deduction" value="0" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '  <div>'
            . '    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">Autres retenues</label>'
            . '    <input type="number" id="ded_other" name="other_deductions" value="0" class="finea-form-control payroll-form-input" />'
            . '  </div>'
            . '</div>'
            . '<div style="margin-bottom: 12px;">'
            . '  <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">Arrondi</label>'
            . '  <input type="number" id="ded_rounding" name="rounding" value="0" class="finea-form-control payroll-form-input" style="max-width: 200px;" />'
            . '</div>'
            . '<div>'
            . '  <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 4px;">Observations</label>'
            . '  <textarea name="observations" id="ded_observations" rows="5" class="finea-form-control payroll-form-input" style="resize: vertical;"></textarea>'
            . '</div>'
            . '</div>';
    }

    // ========================================================================
    //  SIDEBAR — Aperçu du bulletin
    // ========================================================================

    public static function wizardPreviewSidebar(): string
    {
        return '<div class="payroll-preview-sidebar">'
            . '  <p style="color: var(--finea-text-muted); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">APERCU DE PAIE</p>'
            . '  <h3 style="font-size: 20px; font-weight: 600; color: var(--finea-text-dark); margin-bottom: 16px;">Apercu du bulletin</h3>'
            . '  <div id="preview-placeholder" style="color: var(--finea-text-muted); font-size: 14px; line-height: 1.6;">'
            . '    Choisissez un collaborateur, laissez l\'assistant appliquer le profil contrat, puis calculez la fiche de paie avec le pointage mensuel et les heures supplementaires.'
            . '  </div>'
            . '  <div id="preview-table-container" style="display: none;">'
            . '    <table style="width: 100%; font-size: 13px; border-collapse: collapse;">'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Collaborateur</td><td id="preview-emp-name" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Periode</td><td id="preview-period" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Contrat</td><td id="preview-contract" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Salaire Base</td><td id="preview-base" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Heures Sup.</td><td id="preview-overtime" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Primes</td><td id="preview-bonuses" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1; font-weight: 700;"><td style="padding: 10px 0;">Salaire Brut</td><td id="preview-gross" style="padding: 10px 0; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Retenues</td><td id="preview-deductions" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="font-weight: 800; font-size: 15px; border-top: 2px solid #0f172a;"><td style="padding: 12px 0; color: #0f172a;">Salaire NET</td><td id="preview-net" style="padding: 12px 0; text-align: right; color: #0284c7;">-</td></tr>'
            . '    </table>'
            . '  </div>'
            . '</div>';
    }

    // ========================================================================
    //  NAVIGATION
    // ========================================================================

    public static function wizardNavigation(): string
    {
        return '<div class="payroll-wizard-actions" id="wizard-nav">'
            . '<button type="button" id="prev-btn" onclick="prevStep()" disabled class="payroll-nav-btn payroll-nav-btn--prev">'
            . '  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg> Etape precedente'
            . '</button>'
            . '<div style="display: flex; gap: 12px;">'
            . '  <button type="button" id="calc-btn" onclick="calculateBulletin()" style="display: none;" class="payroll-nav-btn payroll-nav-btn--calc">'
            . '    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="8" y1="6" x2="16" y2="6"></line><line x1="8" y1="10" x2="10" y2="10"></line><line x1="14" y1="10" x2="16" y2="10"></line><line x1="8" y1="14" x2="10" y2="14"></line><line x1="14" y1="14" x2="16" y2="14"></line><line x1="8" y1="18" x2="10" y2="18"></line><line x1="14" y1="18" x2="16" y2="18"></line></svg>'
            . '    Calculer le bulletin'
            . '  </button>'
            . '  <button type="submit" id="save-btn" style="display: none;" class="payroll-nav-btn payroll-nav-btn--save">'
            . '    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>'
            . '    Enregistrer la fiche'
            . '  </button>'
            . '  <button type="button" id="next-btn" onclick="nextStep()" class="payroll-nav-btn payroll-nav-btn--next">'
            . '    Etape suivante <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>'
            . '  </button>'
            . '</div>'
            . '</div>';
    }

    // ========================================================================
    //  JAVASCRIPT
    // ========================================================================

    public static function wizardScript(PayrollWizardPage $page): string
    {
        $rulesJson = json_encode($page->contractRules);
        $contractsJson = json_encode($page->employeeContracts);
        $lineItemsJson = json_encode($page->lineItems);
        $attendanceJson = json_encode($page->attendanceSummaries);

        return '<script>
const contractRulesData = ' . $rulesJson . ';
const employeeContractsData = ' . $contractsJson . ';
const lineItemsData = ' . $lineItemsJson . ';
const attendanceSummariesData = ' . $attendanceJson . ';
let currentStep = 1;
let selectedContractType = "libre";
let adjustments = [];

function updateAttendanceAndProrata() {
    const empSelect = document.getElementById("wizard_employee_id");
    const empId = empSelect.value;
    if (!empId) return;
    
    const periodMonth = document.getElementById("wizard_period").value; // e.g. "2026-06"
    
    // Find attendance summary for this employee and month
    const summary = attendanceSummariesData.find(a => a.employee_id == empId && a.month_code === periodMonth);
    
    const contract = employeeContractsData[empId];
    const rule = contractRulesData.find(r => r.contract_type === (contract ? contract.contract_type : selectedContractType)) || {
        working_days: 30,
        mission_rate: 100,
        leave_rate: 100,
        half_day_rate: 50,
        absence_rate: 0,
        sickness_rate: 0,
        rest_rate: 0
    };
    
    let workedDays = parseFloat(rule.working_days) || 30;
    let overtime = 0;
    
    if (summary) {
        // If there are attendance records, calculate based on actual statuses and rates from contract rules
        const present = parseFloat(summary.count_present) || 0;
        const absent = parseFloat(summary.count_absent) || 0;
        const halfDay = parseFloat(summary.count_half_day) || 0;
        const mission = parseFloat(summary.count_mission) || 0;
        const conge = parseFloat(summary.count_conge) || 0;
        const rest = parseFloat(summary.count_rest) || 0;
        
        const missionRate = (parseFloat(rule.mission_rate) || 100) / 100;
        const leaveRate = (parseFloat(rule.leave_rate) || 100) / 100;
        const halfDayRate = (parseFloat(rule.half_day_rate) || 50) / 100;
        const absenceRate = (parseFloat(rule.absence_rate) || 0) / 100;
        const sicknessRate = (parseFloat(rule.sickness_rate) || 0) / 100;
        const restRate = (parseFloat(rule.rest_rate) || 0) / 100;
        
        // Sum worked/paid days prorata
        workedDays = present 
            + (mission * missionRate) 
            + (conge * leaveRate) 
            + (halfDay * halfDayRate) 
            + (absent * absenceRate) 
            + (rest * restRate);
            
        overtime = parseFloat(summary.total_overtime) || 0;
    }
    
    // Update inputs
    document.getElementById("rem_paid_days").value = Number(workedDays).toFixed(2).replace(".", ",");
    document.getElementById("hidden_worked_days").value = workedDays;
    
    document.getElementById("rem_overtime").value = overtime;
}

function onEmployeeChange() {
    const empSelect = document.getElementById("wizard_employee_id");
    const empId = empSelect.value;
    const placeholder = document.getElementById("preview-placeholder");
    const table = document.getElementById("preview-table-container");
    if (!empId) { placeholder.style.display="block"; table.style.display="none"; return; }
    placeholder.style.display="none"; table.style.display="block";
    document.getElementById("preview-emp-name").innerText = empSelect.options[empSelect.selectedIndex].text;
    const period = document.getElementById("wizard_period");
    if (period) document.getElementById("preview-period").innerText = period.value;

    // Load contract data if exists
    const contract = employeeContractsData[empId];
    const alert = document.getElementById("contract-alert");
    if (contract) {
        alert.style.display = "none";
        document.getElementById("contract_base_salary").value = contract.base_salary || 0;
        document.getElementById("contract_sursalaire").value = contract.sursalaire || 0;
        document.getElementById("contract_transport").value = contract.transport_locality || "";
        document.getElementById("rem_salary").value = contract.base_salary || 0;
        document.getElementById("rem_sursalaire").value = contract.sursalaire || 0;
        document.getElementById("rem_category").value = contract.category || "M2";
        
        // Update selected contract type card
        const card = document.querySelector(".payroll-contract-card[data-type=\"" + contract.contract_type + "\"]");
        if (card) {
            selectContractType(card, contract.contract_type);
        } else {
            selectedContractType = contract.contract_type || "libre";
            document.getElementById("preview-contract").innerText = contract.contract_type || "CDI";
        }
        
        document.getElementById("preview-base").innerText = Number(contract.base_salary || 0).toLocaleString("fr-FR") + " XOF";
        // Load line items
        if (contract.line_items) {
            contract.line_items.forEach(function(li) {
                const input = document.querySelector(".line-item-amount[data-code=\"" + li.code + "\"]");
                if (input) input.value = Number(li.amount).toLocaleString("fr-FR");
            });
        }
    } else {
        alert.style.display = "block";
        document.getElementById("contract_base_salary").value = 0;
        document.getElementById("contract_sursalaire").value = 0;
        document.getElementById("contract_transport").value = "";
    }
    
    updateAttendanceAndProrata();
}

function onPeriodChange() {
    const period = document.getElementById("wizard_period");
    if (period) {
        document.getElementById("preview-period").innerText = period.value;
    }
    updateAttendanceAndProrata();
}

function selectContractType(el, type) {
    document.querySelectorAll(".payroll-contract-card").forEach(c => c.classList.remove("selected"));
    el.classList.add("selected");
    selectedContractType = type;
    const rule = contractRulesData.find(r => r.contract_type === type);
    if (!rule) return;
    document.getElementById("active-contract-name").innerText = rule.label;
    document.getElementById("preview-contract").innerText = rule.label;
    // Update rules grid
    const grid = document.getElementById("rules-grid");
    const ruleFields = [
        {label:"JOURS OUVRES (PERIODE)", value: rule.working_days + " jours"},
        {label:"HEURES / JOUR", value: Number(rule.hours_per_day).toFixed(2).replace(".",",") + " h"},
        {label:"MAJORATION HS", value: "x " + Number(rule.overtime_multiplier).toFixed(2).replace(".",",")},
        {label:"PRIME DE PRECARITE AUTOMATIQUE", value: rule.precarity_auto_rate + "%"},
        {label:"MISSION", value: rule.mission_rate + "%"},
        {label:"CONGE", value: rule.leave_rate + "%"},
        {label:"DEMI-JOURNEE", value: rule.half_day_rate + "%"},
        {label:"ABSENCE", value: rule.absence_rate + "%"},
        {label:"MALADIE", value: rule.sickness_rate + "%"},
        {label:"REPOS", value: rule.rest_rate + "%"}
    ];
    grid.innerHTML = "";
    ruleFields.forEach(function(f) {
        grid.innerHTML += "<div class=\"payroll-rules-card\"><p style=\"font-size:11px;color:var(--finea-text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;font-weight:600;\">" + f.label + "</p><p style=\"font-size:18px;font-weight:700;color:var(--finea-text-dark);\">" + f.value + "</p></div>";
    });
    // Update the select
    const sel = document.getElementById("active-contract-select");
    if (sel) {
        sel.innerHTML = "";
        contractRulesData.forEach(function(r) {
            const opt = document.createElement("option");
            opt.value = r.contract_type;
            opt.textContent = r.label;
            if (r.contract_type === type) opt.selected = true;
            sel.appendChild(opt);
        });
    }
}

function onContractSelectChange() {
    const sel = document.getElementById("active-contract-select");
    const card = document.querySelector(".payroll-contract-card[data-type=\"" + sel.value + "\"]");
    if (card) selectContractType(card, sel.value);
}

function addAdjustment() {
    const label = document.getElementById("adj_label").value.trim();
    const nature = document.getElementById("adj_nature").value;
    const amount = parseFloat(document.getElementById("adj_amount").value) || 0;
    if (!label || amount === 0) return;
    adjustments.push({label, nature, amount});
    document.getElementById("adjustments_json").value = JSON.stringify(adjustments);
    document.getElementById("adj_label").value = "";
    document.getElementById("adj_amount").value = "0";
    renderAdjustments();
}

function removeAdjustment(idx) {
    adjustments.splice(idx, 1);
    document.getElementById("adjustments_json").value = JSON.stringify(adjustments);
    renderAdjustments();
}

function renderAdjustments() {
    const list = document.getElementById("adjustments-list");
    if (adjustments.length === 0) { list.innerHTML = ""; return; }
    let html = "<div style=\"border-top:1px solid var(--finea-border);padding-top:12px;\">";
    adjustments.forEach(function(a, i) {
        html += "<div style=\"display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--finea-border);\">"
            + "<span><strong>" + a.label + "</strong> <small style=\"color:var(--finea-text-muted);\">" + a.nature + "</small></span>"
            + "<span style=\"display:flex;align-items:center;gap:12px;\"><strong>" + a.amount.toLocaleString("fr-FR") + " XOF</strong>"
            + "<button type=\"button\" onclick=\"removeAdjustment(" + i + ")\" style=\"background:none;border:none;color:var(--finea-danger);cursor:pointer;font-size:16px;\">✕</button></span>"
            + "</div>";
    });
    list.innerHTML = html + "</div>";
}

function saveContract() {
    const empId = document.getElementById("wizard_employee_id").value;
    if (!empId) { alert("Veuillez d\'abord selectionner un salarie."); return; }
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "' . View::url('rh/paie/contrat') . '";
    const fields = {
        _csrf_token: document.querySelector("input[name=_csrf_token]").value,
        employee_id: empId,
        contract_type: document.getElementById("contract_form_type").value,
        start_date: document.getElementById("contract_start").value,
        end_date: document.getElementById("contract_end").value,
        base_salary: document.getElementById("contract_base_salary").value,
        sursalaire: document.getElementById("contract_sursalaire").value,
        transport_locality: document.getElementById("contract_transport").value,
    };
    for (const [k, v] of Object.entries(fields)) {
        const input = document.createElement("input");
        input.type = "hidden"; input.name = k; input.value = v;
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}

function calculateBulletin() {
    const base = parseFloat(document.getElementById("contract_base_salary").value) || 0;
    const sur = parseFloat(document.getElementById("contract_sursalaire").value) || 0;
    const ot = parseFloat(document.getElementById("rem_overtime").value) || 0;
    const rule = contractRulesData.find(r => r.contract_type === selectedContractType) || {overtime_multiplier:1, working_days:30};
    
    // Prorata calculation
    const workedDays = parseFloat(document.getElementById("hidden_worked_days").value) || 30;
    const normalDays = parseFloat(rule.working_days) || 30;
    const proratedBase = Math.round(base * (workedDays / normalDays));
    
    const otRate = (base / 173.33) * parseFloat(rule.overtime_multiplier);
    const otPay = Math.round(ot * otRate);
    
    // Sum adjustments
    let adjGains = 0;
    let adjDeductions = 0;
    adjustments.forEach(function(adj) {
        if (adj.nature === "gain_exceptionnel" || adj.nature === "prime") {
            adjGains += adj.amount;
        } else if (adj.nature === "retenue_exceptionnelle") {
            adjDeductions += adj.amount;
        } else if (adj.nature === "regularisation") {
            if (adj.amount > 0) adjGains += adj.amount;
            else adjDeductions += Math.abs(adj.amount);
        }
    });

    const gross = proratedBase + sur + otPay + adjGains;

    const isRate = parseFloat(document.querySelector("[name=fiscal_is_salarial_rate]")?.value) || 1.2;
    const cnpsRate = parseFloat(document.querySelector("[name=fiscal_cnps_salarial_rate]")?.value) || 6.3;
    const health = parseFloat(document.getElementById("ded_health").value) || 0;
    const advance = parseFloat(document.getElementById("ded_advance").value) || 0;
    const other = parseFloat(document.getElementById("ded_other").value) || 0;

    const isTax = Math.round(gross * isRate / 100);
    const cnps = Math.round(gross * cnpsRate / 100);
    const totalDed = isTax + cnps + health + advance + other + adjDeductions;
    const net = gross - totalDed;

    document.getElementById("preview-base").innerText = proratedBase.toLocaleString("fr-FR") + " XOF";
    document.getElementById("preview-overtime").innerText = ot + "h (" + otPay.toLocaleString("fr-FR") + " XOF)";
    document.getElementById("preview-bonuses").innerText = (sur + adjGains).toLocaleString("fr-FR") + " XOF";
    document.getElementById("preview-gross").innerText = gross.toLocaleString("fr-FR") + " XOF";
    document.getElementById("preview-deductions").innerText = totalDed.toLocaleString("fr-FR") + " XOF";
    document.getElementById("preview-net").innerText = net.toLocaleString("fr-FR") + " XOF";

    // Set hidden fields for submission
    document.getElementById("hidden_base_salary").value = proratedBase;
    document.getElementById("hidden_bonuses_total").value = sur + otPay + adjGains;
    document.getElementById("hidden_deductions_total").value = totalDed;
    document.getElementById("hidden_net_salary").value = net;
}

function goToStep(step) {
    document.querySelectorAll(".wizard-step-content").forEach(el => el.style.display = "none");
    document.getElementById("wizard-step-" + step).style.display = "block";
    document.querySelectorAll(".payroll-step-tab").forEach((tab, i) => {
        tab.classList.remove("active", "done");
        if (i + 1 === step) tab.classList.add("active");
        else if (i + 1 < step) tab.classList.add("done");
    });
    const prevBtn = document.getElementById("prev-btn");
    const nextBtn = document.getElementById("next-btn");
    const calcBtn = document.getElementById("calc-btn");
    const saveBtn = document.getElementById("save-btn");
    prevBtn.disabled = step === 1;
    prevBtn.style.opacity = step === 1 ? "0.5" : "1";
    prevBtn.style.cursor = step === 1 ? "not-allowed" : "pointer";
    if (step === 4) {
        nextBtn.style.display = "none";
        calcBtn.style.display = "inline-flex";
        saveBtn.style.display = "inline-flex";
    } else {
        nextBtn.style.display = "inline-flex";
        calcBtn.style.display = "none";
        saveBtn.style.display = "none";
    }
    currentStep = step;
}

function nextStep() {
    if (currentStep === 1) {
        const emp = document.getElementById("wizard_employee_id").value;
        if (!emp) { alert("Veuillez selectionner un salarie."); return; }
    }
    if (currentStep < 4) goToStep(currentStep + 1);
}

function prevStep() { if (currentStep > 1) goToStep(currentStep - 1); }

// Initialize: auto-select first contract type card
document.addEventListener("DOMContentLoaded", function() {
    const firstCard = document.querySelector(".payroll-contract-card");
    if (firstCard) selectContractType(firstCard, firstCard.getAttribute("data-type"));
});
</script>';
    }

    // ========================================================================
    //  PAYROLL INDEX PAGE (unchanged)
    // ========================================================================

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
                    Ui::button('Nouvelle fiche de paie', [
                        'href' => 'rh/paie/nouveau',
                        'variant' => 'primary',
                    ]),
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
                . '<td style="padding: 10px;"><strong>' . View::e((string)$row['full_name']) . '</strong><small style="display: block; color: var(--finea-text-muted);">' . View::e((string)($row['employee_number'] ?: 'Sans')) . '</small></td>'
                . '<td style="padding: 10px;"><input type="number" step="0.5" name="records[' . $empId . '][worked_days]" value="' . (float)$row['worked_days'] . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '></td>'
                . '<td style="padding: 10px;"><input type="number" step="0.5" name="records[' . $empId . '][overtime_hours]" value="' . (float)$row['overtime_hours'] . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '></td>'
                . '<td style="padding: 10px;"><input type="number" name="records[' . $empId . '][bonus]" value="' . (float)$row['bonus'] . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '></td>'
                . '<td style="padding: 10px;"><input type="number" name="records[' . $empId . '][deductions]" value="' . (float)$row['deductions'] . '" class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '></td>'
                . '<td style="padding: 10px;"><input type="text" name="records[' . $empId . '][notes]" value="' . View::e((string)$row['notes']) . '" placeholder="Note..." class="finea-form-control" style="width: 100%; padding: 5px; border: 1px solid var(--finea-border); border-radius: 4px;" ' . $disabled . '></td>'
                . '</tr>';
        }

        $tableHtml = '<div style="overflow-x: auto;">'
            . '<table class="finea-table" style="width: 100%; border-collapse: collapse; text-align: left;">'
            . '<thead><tr style="background: var(--finea-background-light); border-bottom: 2px solid var(--finea-border);">'
            . '<th style="padding: 10px;">Collaborateur</th><th style="padding: 10px; width: 90px;">Jours trav.</th><th style="padding: 10px; width: 90px;">H. Sup</th><th style="padding: 10px; width: 110px;">Primes</th><th style="padding: 10px; width: 110px;">Retenues</th><th style="padding: 10px;">Notes</th>'
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>';

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
                . '<td style="padding: 10px;"><strong>' . View::e((string)$slip['full_name']) . '</strong><small style="display: block; color: var(--finea-text-muted);">' . View::e((string)($slip['employee_number'] ?: 'Sans')) . '</small></td>'
                . '<td style="padding: 10px;">' . number_format((float)$slip['base_salary'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="padding: 10px; color: var(--finea-success);">+' . number_format((float)$slip['bonuses_total'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="padding: 10px; color: var(--finea-danger);">-' . number_format((float)$slip['deductions_total'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="padding: 10px;"><strong style="color: var(--finea-blue);">' . number_format((float)$slip['net_salary'], 0, ',', ' ') . ' XOF</strong></td>'
                . '</tr>';
        }

        $tableHtml = '<div style="margin-top: 15px; overflow-x: auto;">'
            . '<table class="finea-table" style="width: 100%; border-collapse: collapse; text-align: left;">'
            . '<thead><tr style="background: var(--finea-background-light); border-bottom: 2px solid var(--finea-border);">'
            . '<th style="padding: 10px;">Collaborateur</th><th style="padding: 10px; width: 120px;">Salaire de Base</th><th style="padding: 10px; width: 100px;">Primes/HS</th><th style="padding: 10px; width: 100px;">Retenues</th><th style="padding: 10px; width: 120px;">Salaire Net</th>'
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>';

        return Rh::card($tableHtml, [
            'title' => 'Bulletins de salaire generes',
            'eyebrow' => 'Calculs effectues',
        ]);
    }

    private static function formatPeriodStatus(string $status): string
    {
        return ['open' => 'Ouverte', 'calculating' => 'Calculee', 'closed' => 'Cloturee'][$status] ?? ucfirst($status);
    }
}
