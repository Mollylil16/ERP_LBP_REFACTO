<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\ContractAllowanceRow;
use App\View\Components\Form;
use App\View\Components\Ui;

$contract = isset($viewData) ? $viewData->array('contract') : [];
$employees = isset($viewData) ? $viewData->array('employees') : [];
$formAction = isset($viewData) ? $viewData->string('formAction') : '';
$submitLabel = isset($viewData) ? $viewData->string('submitLabel', 'Enregistrer') : 'Enregistrer';
$pageTitle = isset($viewData) ? $viewData->string('pageTitle', 'Contrat') : 'Contrat';
/** @var array<int,array<string,mixed>> $allowances */
$allowances = is_array($contract['allowances'] ?? null) ? $contract['allowances'] : [];

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Contrat',
            $pageTitle,
            'Renseignez les détails du contrat, le salaire de base et les indemnités.',
            Ui::button('Retour', ['href' => 'rh/contrats', 'variant' => 'plain']),
            ['class' => 'rh-hero']
        ) ?>

        <form method="post" action="<?= View::url(ltrim($formAction, '/')) ?>" class="rh-contract-form">
            <?= Csrf::input() ?>

            <section class="finea-section-card">
                <h2 class="finea-section-title">Informations principales</h2>
                <div class="rh-form-grid">
                    <?= Form::selectSearch(
                        'employee_id',
                        $employees,
                        $contract['employee_id'] ?? '',
                        ['label' => 'Employé', 'required' => true]
                    ) ?>
                    <?= Form::select('contract_type', [
                        ['value' => 'CDI', 'label' => 'CDI'],
                        ['value' => 'CDD', 'label' => 'CDD'],
                        ['value' => 'Stage', 'label' => 'Stage'],
                        ['value' => 'Interim', 'label' => 'Intérim'],
                    ], $contract['contract_type'] ?? 'CDI', ['label' => 'Type de contrat', 'required' => true]) ?>
                    <?= Form::input('start_date', [
                        'label' => 'Date de début',
                        'type' => 'date',
                        'value' => (string) ($contract['start_date'] ?? ''),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('end_date', [
                        'label' => 'Date de fin',
                        'type' => 'date',
                        'value' => (string) ($contract['end_date'] ?? ''),
                        'hint' => 'Laissez vide pour un CDI.',
                    ]) ?>
                    <?= Form::input('trial_end_date', [
                        'label' => "Fin de période d'essai",
                        'type' => 'date',
                        'value' => (string) ($contract['trial_end_date'] ?? ''),
                    ]) ?>
                    <?= Form::select('status', [
                        ['value' => 'active', 'label' => 'En cours'],
                        ['value' => 'terminated', 'label' => 'Terminé'],
                        ['value' => 'renewed', 'label' => 'Renouvelé'],
                    ], $contract['status'] ?? 'active', ['label' => 'Statut']) ?>
                </div>
            </section>

            <section class="finea-section-card">
                <div class="rh-section-heading">
                    <div>
                        <p class="rh-eyebrow">Rémunération</p>
                        <h2 class="finea-section-title">Salaire et indemnités fixes</h2>
                    </div>
                    <?= Ui::button('Ajouter une indemnité', ['variant' => 'secondary', 'type' => 'button', 'data-contract-add-allowance' => true]) ?>
                </div>
                <div class="rh-contract-salary">
                    <?= Form::input('base_salary', [
                        'label' => 'Salaire de base mensuel (FCFA)',
                        'type' => 'number',
                        'step' => '0.01',
                        'min' => '0',
                        'value' => (string) ($contract['base_salary'] ?? '0'),
                        'required' => true,
                    ]) ?>
                </div>

                <div class="rh-allowance-list" data-contract-allowances>
                    <?php if ($allowances === []): ?>
                        <?= ContractAllowanceRow::render(0) ?>
                    <?php else: ?>
                        <?php foreach ($allowances as $index => $allowance): ?>
                            <?= ContractAllowanceRow::render($index, $allowance) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <template data-contract-allowance-template>
                    <?= ContractAllowanceRow::render('__INDEX__') ?>
                </template>
            </section>

            <div class="rh-form-actions">
                <?= Ui::button('Annuler', ['href' => 'rh/contrats', 'variant' => 'secondary']) ?>
                <?= Ui::button($submitLabel, ['variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
