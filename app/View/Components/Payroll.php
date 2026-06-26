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

    public static function wizardPage(\App\View\Pages\Rh\PayrollWizardPage $page, string $csrfToken, array $contracts = []): string
    {
        $heroActions = [
            '<a href="' . View::url('rh/pointage') . '" class="finea-btn" style="background: white; color: var(--finea-primary); font-weight: 600; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg> Pointage journalier</a>',
            '<a href="' . View::url('rh/regles-contrats') . '" class="finea-btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); font-weight: 500; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg> Regles contrats</a>',
            '<a href="' . View::url('rh/paie') . '" class="finea-btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); font-weight: 500; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg> Moteur de paie CI</a>',
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

        $stepperHtml = '<div style="display: flex; gap: 12px; margin-bottom: 30px; overflow-x: auto; padding-bottom: 5px;">'
            . '<div id="step-tab-1" style="background: #0B1120; color: white; padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 14px; white-space: nowrap; transition: all 0.2s;">1. Salarie</div>'
            . '<div id="step-tab-2" style="background: white; color: var(--finea-text-muted); padding: 8px 16px; border-radius: 20px; font-weight: 500; font-size: 14px; white-space: nowrap; border: 1px solid var(--finea-border); transition: all 0.2s;">2. Contrat</div>'
            . '<div id="step-tab-3" style="background: white; color: var(--finea-text-muted); padding: 8px 16px; border-radius: 20px; font-weight: 500; font-size: 14px; white-space: nowrap; border: 1px solid var(--finea-border); transition: all 0.2s;">3. Gains</div>'
            . '<div id="step-tab-4" style="background: white; color: var(--finea-text-muted); padding: 8px 16px; border-radius: 20px; font-weight: 500; font-size: 14px; white-space: nowrap; border: 1px solid var(--finea-border); transition: all 0.2s;">4. Retenues</div>'
            . '</div>';

        $employeeOptions = '<option value="">Selectionner un collaborateur</option>';
        foreach ($page->employees as $emp) {
            $employeeOptions .= '<option value="' . (int)$emp['id'] . '">' . View::e($emp['full_name']) . ' (' . View::e($emp['employee_number'] ?: 'Sans matricule') . ')</option>';
        }

        $periodOptions = '';
        foreach ($page->periods as $p) {
            $periodOptions .= '<option value="' . (int)$p['id'] . '">' . View::e($p['code']) . '</option>';
        }

        // Hidden fields and structures for steps
        $formHtml = '<form method="post" action="' . View::url('rh/paie/nouveau') . '" class="rh-payroll-wizard-form">'
            . Form::hidden('_csrf_token', $csrfToken)
            
            // Step 1: Salarié
            . '<div id="wizard-step-1" class="wizard-step-content">'
            . '  <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px;">'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Salarie</label>'
            . '      <select id="wizard_employee_id" name="employee_id" class="finea-form-control" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; outline: none; background: white; font-size: 15px;" onchange="updateEmployeeContract()">'
            . $employeeOptions
            . '      </select>'
            . '    </div>'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Periode</label>'
            . '      <select id="wizard_period_id" name="period_id" class="finea-form-control" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; outline: none; background: white; font-size: 15px;" onchange="updatePeriodPreview()">'
            . $periodOptions
            . '      </select>'
            . '    </div>'
            . '  </div>'
            . '  <div style="border: 1px dashed var(--finea-border-dark); border-radius: 12px; padding: 20px; color: var(--finea-text-muted); font-size: 14px; line-height: 1.5; background: rgba(0,0,0,0.01); margin-bottom: 30px;">'
            . '    Selectionnez un collaborateur et une periode. L\'assistant chargera ensuite le pointage mensuel, les heures supplementaires et les regles de paie associees.'
            . '  </div>'
            . '</div>'

            // Step 2: Contrat
            . '<div id="wizard-step-2" class="wizard-step-content" style="display: none;">'
            . '  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Type de contrat</label>'
            . '      <input type="text" id="contract_type_display" class="finea-form-control" readonly style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; background-color: #f1f5f9; outline: none; font-size: 15px;">'
            . '    </div>'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Salaire de base contractuel (XOF)</label>'
            . '      <input type="number" id="base_salary" name="base_salary" class="finea-form-control" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; outline: none; font-size: 15px;" oninput="calculateProrata()">'
            . '    </div>'
            . '  </div>'
            . '  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Jours travailles / pointes (sur 30)</label>'
            . '      <input type="number" step="0.5" id="worked_days" name="worked_days" value="30" class="finea-form-control" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; outline: none; font-size: 15px;" oninput="calculateProrata()">'
            . '    </div>'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Salaire de base proratise (XOF)</label>'
            . '      <input type="number" id="prorated_salary" class="finea-form-control" readonly style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; background-color: #f1f5f9; outline: none; font-size: 15px;">'
            . '    </div>'
            . '  </div>'
            . '</div>'

            // Step 3: Gains
            . '<div id="wizard-step-3" class="wizard-step-content" style="display: none;">'
            . '  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Heures supplementaires (HS)</label>'
            . '      <input type="number" step="0.5" id="overtime_hours" name="overtime_hours" value="0" class="finea-form-control" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; outline: none; font-size: 15px;" oninput="calculateEarnings()">'
            . '    </div>'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Valorisation HS (Taux 125%)</label>'
            . '      <input type="number" id="overtime_pay" class="finea-form-control" readonly style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; background-color: #f1f5f9; outline: none; font-size: 15px;">'
            . '    </div>'
            . '  </div>'
            . '  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Primes & indemnites (ex: Transport)</label>'
            . '      <input type="number" id="bonuses_total" name="bonuses_total" value="0" class="finea-form-control" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; outline: none; font-size: 15px;" oninput="calculateEarnings()">'
            . '    </div>'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Salaire Brut Social (SBS) (XOF)</label>'
            . '      <input type="number" id="gross_salary" class="finea-form-control" readonly style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; background-color: #f1f5f9; outline: none; font-size: 15px;">'
            . '    </div>'
            . '  </div>'
            . '</div>'

            // Step 4: Retenues
            . '<div id="wizard-step-4" class="wizard-step-content" style="display: none;">'
            . '  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Retenue CNPS (6.3% du Brut)</label>'
            . '      <input type="number" id="cnps_deduction" class="finea-form-control" readonly style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; background-color: #f1f5f9; outline: none; font-size: 15px;">'
            . '    </div>'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Retenue CMU (Forfaitaire)</label>'
            . '      <input type="number" id="cmu_deduction" class="finea-form-control" readonly style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; background-color: #f1f5f9; outline: none; font-size: 15px;">'
            . '    </div>'
            . '  </div>'
            . '  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Autres retenues exceptionnelles</label>'
            . '      <input type="number" id="deductions_total" name="deductions_total" value="0" class="finea-form-control" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; outline: none; font-size: 15px;" oninput="calculateDeductions()">'
            . '    </div>'
            . '    <div class="form-group">'
            . '      <label class="finea-form-label" style="font-weight: 600; margin-bottom: 8px; display: block;">Net a payer (XOF)</label>'
            . '      <input type="number" id="net_salary" name="net_salary" class="finea-form-control" readonly style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; background-color: #e0f2fe; color: #0369a1; font-weight: 700; outline: none; font-size: 15px;">'
            . '    </div>'
            . '  </div>'
            . '</div>'

            // Next / Prev Actions
            . '<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; border-top: 1px solid var(--finea-border); padding-top: 20px;">'
            . '  <button type="button" id="prev-btn" onclick="prevStep()" disabled style="background: white; border: 1px solid var(--finea-border); color: var(--finea-text-muted); padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: not-allowed; display: inline-flex; align-items: center; gap: 8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg> Etape precedente</button>'
            . '  <button type="button" id="next-btn" onclick="nextStep()" style="background: #0B1120; border: none; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">Etape suivante <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg></button>'
            . '</div>'
            . '</form>';

        $leftCard = '<div style="background: #F8FAFC; border: 1px solid var(--finea-border); border-radius: 16px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">'
            . $stepperHtml
            . $formHtml
            . '</div>';

        // Right side preview block
        $rightCard = '<div style="background: #F1F5F9; border: 1px solid var(--finea-border); border-radius: 16px; padding: 30px; height: fit-content; position: sticky; top: 20px;">'
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
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Base (Proratise)</td><td id="preview-base" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Heures Sup.</td><td id="preview-overtime" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Primes</td><td id="preview-bonuses" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1; font-weight: 700;"><td style="padding: 10px 0;">Salaire Brut (SBS)</td><td id="preview-gross" style="padding: 10px 0; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">CNPS & CMU</td><td id="preview-social" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="border-bottom: 1px solid #cbd5e1;"><td style="padding: 8px 0; color: #64748b;">Autres retenues</td><td id="preview-deductions" style="padding: 8px 0; font-weight: 600; text-align: right;">-</td></tr>'
            . '      <tr style="font-weight: 800; font-size: 15px; border-top: 2px solid #0f172a;"><td style="padding: 12px 0; color: #0f172a;">Salaire NET</td><td id="preview-net" style="padding: 12px 0; text-align: right; color: #0284c7;">-</td></tr>'
            . '    </table>'
            . '  </div>'
            . '</div>';

        // Embedded Javascript code for calculations and transitions
        $script = '<script>'
            . 'const contractsData = ' . json_encode($contracts) . ';'
            . 'let currentStep = 1;'
            . 'function updateEmployeeContract() {'
            . '  const empSelect = document.getElementById("wizard_employee_id");'
            . '  const employeeId = empSelect.value;'
            . '  const previewPlaceholder = document.getElementById("preview-placeholder");'
            . '  const previewTable = document.getElementById("preview-table-container");'
            . '  if (!employeeId) {'
            . '    previewPlaceholder.style.display = "block";'
            . '    previewTable.style.display = "none";'
            . '    return;'
            . '  }'
            . '  previewPlaceholder.style.display = "none";'
            . '  previewTable.style.display = "block";'
            . '  document.getElementById("preview-emp-name").innerText = empSelect.options[empSelect.selectedIndex].text;'
            . '  updatePeriodPreview();'
            . '  const contract = contractsData[employeeId] || { type: "CDI", salary: 150000 };'
            . '  document.getElementById("contract_type_display").value = contract.type || "CDI";'
            . '  document.getElementById("base_salary").value = contract.salary || 150000;'
            . '  document.getElementById("preview-contract").innerText = contract.type || "CDI";'
            . '  calculateProrata();'
            . '}'
            . 'function updatePeriodPreview() {'
            . '  const perSelect = document.getElementById("wizard_period_id");'
            . '  if (perSelect && perSelect.value) {'
            . '    document.getElementById("preview-period").innerText = perSelect.options[perSelect.selectedIndex].text;'
            . '  }'
            . '}'
            . 'function calculateProrata() {'
            . '  const baseSalary = parseFloat(document.getElementById("base_salary").value) || 0;'
            . '  const workedDays = parseFloat(document.getElementById("worked_days").value) || 30;'
            . '  const prorated = Math.round(baseSalary * (workedDays / 30));'
            . '  document.getElementById("prorated_salary").value = prorated;'
            . '  document.getElementById("preview-base").innerText = prorated.toLocaleString("fr-FR") + " XOF";'
            . '  calculateEarnings();'
            . '}'
            . 'function calculateEarnings() {'
            . '  const prorated = parseFloat(document.getElementById("prorated_salary").value) || 0;'
            . '  const baseSalary = parseFloat(document.getElementById("base_salary").value) || 0;'
            . '  const otHours = parseFloat(document.getElementById("overtime_hours").value) || 0;'
            . '  const otRate = (baseSalary / 173.33) * 1.25;'
            . '  const otPay = Math.round(otHours * otRate);'
            . '  document.getElementById("overtime_pay").value = otPay;'
            . '  const bonuses = parseFloat(document.getElementById("bonuses_total").value) || 0;'
            . '  const gross = prorated + otPay + bonuses;'
            . '  document.getElementById("gross_salary").value = gross;'
            . '  document.getElementById("preview-overtime").innerText = otHours + "h (" + otPay.toLocaleString("fr-FR") + " XOF)";'
            . '  document.getElementById("preview-bonuses").innerText = bonuses.toLocaleString("fr-FR") + " XOF";'
            . '  document.getElementById("preview-gross").innerText = gross.toLocaleString("fr-FR") + " XOF";'
            . '  calculateDeductions();'
            . '}'
            . 'function calculateDeductions() {'
            . '  const gross = parseFloat(document.getElementById("gross_salary").value) || 0;'
            . '  const cnps = Math.round(gross * 0.063);'
            . '  const cmu = gross > 0 ? 500 : 0;'
            . '  document.getElementById("cnps_deduction").value = cnps;'
            . '  document.getElementById("cmu_deduction").value = cmu;'
            . '  const otherDeductions = parseFloat(document.getElementById("deductions_total").value) || 0;'
            . '  const totalDeductions = cnps + cmu + otherDeductions;'
            . '  const net = gross - totalDeductions;'
            . '  document.getElementById("net_salary").value = net;'
            . '  document.getElementById("preview-social").innerText = (cnps + cmu).toLocaleString("fr-FR") + " XOF";'
            . '  document.getElementById("preview-deductions").innerText = otherDeductions.toLocaleString("fr-FR") + " XOF";'
            . '  document.getElementById("preview-net").innerText = net.toLocaleString("fr-FR") + " XOF";'
            . '}'
            . 'function goToStep(step) {'
            . '  document.querySelectorAll(".wizard-step-content").forEach(el => el.style.display = "none");'
            . '  document.getElementById("wizard-step-" + step).style.display = "block";'
            . '  for (let i = 1; i <= 4; i++) {'
            . '    const tab = document.getElementById("step-tab-" + i);'
            . '    if (i === step) {'
            . '      tab.style.background = "#0B1120";'
            . '      tab.style.color = "white";'
            . '    } else if (i < step) {'
            . '      tab.style.background = "#e2e8f0";'
            . '      tab.style.color = "#0f172a";'
            . '    } else {'
            . '      tab.style.background = "white";'
            . '      tab.style.color = "var(--finea-text-muted)";'
            . '    }'
            . '  }'
            . '  const prevBtn = document.getElementById("prev-btn");'
            . '  const nextBtn = document.getElementById("next-btn");'
            . '  if (step === 1) {'
            . '    prevBtn.disabled = true;'
            . '    prevBtn.style.cursor = "not-allowed";'
            . '    prevBtn.style.color = "var(--finea-text-muted)";'
            . '  } else {'
            . '    prevBtn.disabled = false;'
            . '    prevBtn.style.cursor = "pointer";'
            . '    prevBtn.style.color = "#0f172a";'
            . '  }'
            . '  if (step === 4) {'
            . '    nextBtn.innerHTML = "Générer le bulletin <svg width=\'18\' height=\'18\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' viewBox=\'0 0 24 24\'><path d=\'M5 13l4 4L19 7\'></path></svg>";'
            . '    nextBtn.style.background = "#10B981";'
            . '  } else {'
            . '    nextBtn.innerHTML = "Etape suivante <svg width=\'18\' height=\'18\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' viewBox=\'0 0 24 24\'><path d=\'M14 5l7 7m0 0l-7 7m7-7H3\'></path></svg>";'
            . '    nextBtn.style.background = "#0B1120";'
            . '  }'
            . '  currentStep = step;'
            . '}'
            . 'function nextStep() {'
            . '  if (currentStep === 1) {'
            . '    const emp = document.getElementById("wizard_employee_id").value;'
            . '    const per = document.getElementById("wizard_period_id").value;'
            . '    if (!emp || !per) { alert("Veuillez sélectionner un salarié et une période."); return; }'
            . '  }'
            . '  if (currentStep < 4) {'
            . '    goToStep(currentStep + 1);'
            . '  } else {'
            . '    document.querySelector(".rh-payroll-wizard-form").submit();'
            . '  }'
            . '}'
            . 'function prevStep() {'
            . '  if (currentStep > 1) {'
            . '    goToStep(currentStep - 1);'
            . '  }'
            . '}'
            . '</script>';

        return '<div class="finea-shell rh-payroll-wizard-page">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 24px;">'
            . $leftCard
            . $rightCard
            . '</div>'
            . '</div>'
            . '</div>'
            . $script;
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
