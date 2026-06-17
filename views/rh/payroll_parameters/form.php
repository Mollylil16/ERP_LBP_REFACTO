<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

require BASE_PATH . '/views/rh/_navigation.php';

$param = isset($viewData) ? $viewData->array('param') : ($param ?? []);
$formAction = isset($viewData) ? $viewData->string('formAction') : ($formAction ?? '');
$submitLabel = isset($viewData) ? $viewData->string('submitLabel') : ($submitLabel ?? 'Enregistrer');

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Légal & Fiscal',
            View::e($viewData->string('pageTitle', 'Paramétrage Paie')),
            'Configuration des variables appliquées au moteur de paie.',
            Ui::button('Retour', ['href' => 'rh/parametres-paie', 'variant' => 'plain']),
            ['class' => 'rh-hero']
        ) ?>

        <form method="post" action="<?= View::url(ltrim($formAction, '/')) ?>" class="finea-grid">
            <?= Csrf::input() ?>
            
            <section class="finea-section-card">
                <h3 class="finea-section-title">Général & Plafonds</h3>
                <div class="rh-form-grid">
                    <?= Form::input('year', [
                        'label' => 'Année d\'application *',
                        'type' => 'number',
                        'min' => '2000',
                        'max' => '2100',
                        'value' => (string)($param['year'] ?? date('Y')),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('smig', [
                        'label' => 'SMIG (FCFA) *',
                        'type' => 'number',
                        'step' => '0.01',
                        'value' => (string)($param['smig'] ?? '75000'),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('cnps_ceiling', [
                        'label' => 'Plafond Annuel CNPS (FCFA) *',
                        'type' => 'number',
                        'step' => '0.01',
                        'value' => (string)($param['cnps_ceiling'] ?? '1647315'),
                        'required' => true,
                        'hint' => 'Plafond mensuel x 12. En général: 1 647 315 FCFA.'
                    ]) ?>
                </div>
            </section>

            <section class="finea-section-card">
                <h3 class="finea-section-title">Taux de Cotisation (%)</h3>
                <div class="rh-form-grid">
                    <?= Form::input('cnps_employee_rate', [
                        'label' => 'Taux CNPS Retraite (Salarial) *',
                        'type' => 'number',
                        'step' => '0.01',
                        'value' => (string)($param['cnps_employee_rate'] ?? '3.20'),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('cnps_employer_rate', [
                        'label' => 'Taux CNPS Retraite (Patronal) *',
                        'type' => 'number',
                        'step' => '0.01',
                        'value' => (string)($param['cnps_employer_rate'] ?? '7.70'),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('cmu_employee_rate', [
                        'label' => 'Taux CMU (Salarial) *',
                        'type' => 'number',
                        'step' => '0.01',
                        'value' => (string)($param['cmu_employee_rate'] ?? '2.00'),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('cmu_employer_rate', [
                        'label' => 'Taux CMU (Patronal) *',
                        'type' => 'number',
                        'step' => '0.01',
                        'value' => (string)($param['cmu_employer_rate'] ?? '2.00'),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('cn_rate', [
                        'label' => 'Contribution Nationale (CN) *',
                        'type' => 'number',
                        'step' => '0.01',
                        'value' => (string)($param['cn_rate'] ?? '1.50'),
                        'required' => true,
                    ]) ?>
                </div>
            </section>

            <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 16px;">
                <?= Ui::button('Annuler', ['href' => 'rh/parametres-paie', 'variant' => 'secondary']) ?>
                <?= Ui::button($submitLabel, ['variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
