<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();

$typeOptions = [];
foreach ($leaveTypes as $t) {
    $deductInfo = $t['deduct_from_balance'] ? ' (Déduit du solde)' : '';
    $typeOptions[] = ['value' => (string) $t['id'], 'label' => $t['name'] . $deductInfo];
}

$employeeOptions = [];
if ($isHR) {
    foreach ($employees as $e) {
        $employeeOptions[] = ['value' => (string) $e['id'], 'label' => $e['employee_number'] . ' - ' . $e['full_name']];
    }
}
?>

<?= Ui::pageHeader(
    'Congés',
    'Nouvelle Demande de Congé / Absence',
    'Veuillez remplir le formulaire ci-dessous pour soumettre votre demande.',
    Ui::button('Retour', ['href' => 'rh/conges', 'variant' => 'secondary']),
    ['class' => 'rh-hero']
) ?>

<div class="finea-section-card">
    <form action="<?= View::url('rh/conges/nouveau') ?>" method="post" style="max-width: 600px;">
        <?= Csrf::input() ?>

        <?php if ($isHR): ?>
            <?= Form::select('employee_id', $employeeOptions, (string) $actorId, [
                'label' => 'Employé (Saisie RH)',
                'required' => true,
            ]) ?>
        <?php else: ?>
            <?= Form::hidden('employee_id', (string) $actorId) ?>
        <?php endif; ?>

        <?= Form::select('leave_type_id', $typeOptions, '', [
            'label' => 'Type de congé/absence',
            'required' => true
        ]) ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <?= Form::input('start_date', [
                'label' => 'Date de début',
                'type' => 'date',
                'required' => true
            ]) ?>
            <?= Form::input('end_date', [
                'label' => 'Date de fin (inclus)',
                'type' => 'date',
                'required' => true
            ]) ?>
        </div>

        <?= Form::textarea('reason', ['label' => 'Motif / Commentaire (Optionnel)', 'rows' => 3]) ?>

        <div style="margin-top: 2rem;">
            <?= Ui::button('Soumettre la demande', ['variant' => 'primary', 'type' => 'submit']) ?>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
