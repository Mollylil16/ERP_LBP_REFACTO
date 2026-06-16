<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

$contract = isset($viewData) ? $viewData->array('contract') : ($contract ?? []);
$employees = isset($viewData) ? $viewData->array('employees') : ($employees ?? []);
$formAction = isset($viewData) ? $viewData->string('formAction') : ($formAction ?? '');
$submitLabel = isset($viewData) ? $viewData->string('submitLabel') : ($submitLabel ?? 'Enregistrer');
$allowances = $contract['allowances'] ?? [];

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Contrat',
            View::e($viewData->string('pageTitle', 'Nouveau Contrat')),
            'Renseignez les détails du contrat, le salaire de base et les indemnités.',
            Ui::button('Retour', ['href' => 'rh/contrats', 'variant' => 'plain']),
            ['class' => 'rh-hero']
        ) ?>

        <form method="post" action="<?= View::url(ltrim($formAction, '/')) ?>" class="finea-grid">
            <?= Csrf::input() ?>

            <section class="finea-section-card">
                <h3 class="finea-section-title">Informations Principales</h3>
                <div class="rh-form-grid">
                    <?= Form::selectSearch('employee_id', $employees, [
                        'label' => 'Employé *',
                        'value' => (string)($contract['employee_id'] ?? ''),
                        'required' => true,
                    ]) ?>
                    <?= Form::select('contract_type', [
                        'CDI' => 'CDI (Durée Indéterminée)',
                        'CDD' => 'CDD (Durée Déterminée)',
                        'Stage' => 'Stage',
                        'Interim' => 'Intérim',
                    ], [
                        'label' => 'Type de contrat *',
                        'value' => (string)($contract['contract_type'] ?? 'CDI'),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('start_date', [
                        'label' => 'Date de début *',
                        'type' => 'date',
                        'value' => (string)($contract['start_date'] ?? ''),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('end_date', [
                        'label' => 'Date de fin (Si CDD/Stage)',
                        'type' => 'date',
                        'value' => (string)($contract['end_date'] ?? ''),
                    ]) ?>
                    <?= Form::input('trial_end_date', [
                        'label' => "Date de fin de période d'essai",
                        'type' => 'date',
                        'value' => (string)($contract['trial_end_date'] ?? ''),
                    ]) ?>
                    <?= Form::select('status', [
                        'active' => 'En cours',
                        'terminated' => 'Terminé',
                        'renewed' => 'Renouvelé',
                    ], [
                        'label' => 'Statut',
                        'value' => (string)($contract['status'] ?? 'active'),
                    ]) ?>
                </div>
            </section>

            <section class="finea-section-card">
                <h3 class="finea-section-title">Rémunération</h3>
                <div class="rh-form-grid" style="grid-template-columns: 1fr;">
                    <?= Form::input('base_salary', [
                        'label' => 'Salaire de base mensuel (FCFA) *',
                        'type' => 'number',
                        'step' => '0.01',
                        'value' => (string)($contract['base_salary'] ?? '0'),
                        'required' => true,
                        'style' => 'font-weight: bold; font-size: 1.2rem;'
                    ]) ?>
                </div>

                <div class="rh-allowances-section" style="margin-top: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="margin: 0; color: var(--finea-navy);">Indemnités Fixes</h4>
                        <button type="button" class="finea-action-btn finea-action-btn--secondary" onclick="addAllowanceRow()">
                            + Ajouter une indemnité
                        </button>
                    </div>
                    
                    <div id="allowances_container">
                        <?php if (empty($allowances)): ?>
                            <div class="allowance-row" style="display: flex; gap: 12px; margin-bottom: 12px; align-items: end;">
                                <div style="flex: 2;"><?= Form::input('allowance_name[]', ['label' => 'Libellé (ex: Transport)', 'placeholder' => 'Nom de l\'indemnité']) ?></div>
                                <div style="flex: 1.5;"><?= Form::input('allowance_amount[]', ['label' => 'Montant (FCFA)', 'type' => 'number', 'step' => '0.01']) ?></div>
                                <div style="flex: 1; margin-bottom: 10px;"><?= Form::checkbox('allowance_taxable[]', 'Imposable (Soumis ITS)', '1', false) ?></div>
                                <button type="button" class="finea-action-btn finea-action-btn--danger" onclick="this.parentElement.remove()" style="margin-bottom: 4px;">X</button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($allowances as $index => $allowance): ?>
                                <div class="allowance-row" style="display: flex; gap: 12px; margin-bottom: 12px; align-items: end;">
                                    <div style="flex: 2;"><?= Form::input('allowance_name[]', ['label' => $index === 0 ? 'Libellé' : false, 'value' => (string)($allowance['name'] ?? '')]) ?></div>
                                    <div style="flex: 1.5;"><?= Form::input('allowance_amount[]', ['label' => $index === 0 ? 'Montant (FCFA)' : false, 'type' => 'number', 'step' => '0.01', 'value' => (string)($allowance['amount'] ?? '')]) ?></div>
                                    <div style="flex: 1; margin-bottom: 10px;"><?= Form::checkbox('allowance_taxable_' . $index, 'Imposable', '1', !empty($allowance['is_taxable']), ['name' => 'allowance_taxable['.$index.']']) ?></div>
                                    <button type="button" class="finea-action-btn finea-action-btn--danger" onclick="this.parentElement.remove()" style="margin-bottom: 4px;">X</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 16px;">
                <?= Ui::button('Annuler', ['href' => 'rh/contrats', 'variant' => 'secondary']) ?>
                <?= Ui::button($submitLabel, ['variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </form>
    </div>
</div>

<script>
    let allowanceIndex = <?= count($allowances) ?: 1 ?>;
    function addAllowanceRow() {
        const container = document.getElementById('allowances_container');
        const row = document.createElement('div');
        row.className = 'allowance-row';
        row.style.cssText = 'display: flex; gap: 12px; margin-bottom: 12px; align-items: end;';
        row.innerHTML = `
            <div style="flex: 2;"><div class="finea-field"><input type="text" name="allowance_name[]" class="finea-input" placeholder="Nouvelle indemnité"></div></div>
            <div style="flex: 1.5;"><div class="finea-field"><input type="number" name="allowance_amount[]" class="finea-input" step="0.01" placeholder="Montant"></div></div>
            <div style="flex: 1; margin-bottom: 10px;">
                <label class="finea-checkbox"><input type="checkbox" name="allowance_taxable[${allowanceIndex}]" value="1"> <span>Imposable</span></label>
            </div>
            <button type="button" class="finea-action-btn finea-action-btn--danger" onclick="this.parentElement.remove()" style="margin-bottom: 4px;">X</button>
        `;
        container.appendChild(row);
        allowanceIndex++;
    }
</script>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
