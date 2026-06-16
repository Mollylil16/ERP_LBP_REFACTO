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
    $typeOptions[$t['id']] = $t['name'] . $deductInfo;
}

$employeeOptions = [];
if ($isHR) {
    foreach ($employees as $e) {
        $employeeOptions[$e['id']] = $e['employee_number'] . ' - ' . $e['full_name'];
    }
}
?>

<?= Ui::pageHeader(
    'Nouvelle Demande de Congé / Absence',
    'Veuillez remplir le formulaire ci-dessous pour soumettre votre demande.',
    ['actions' => '
        <a href="/rh/conges" class="finea-action-btn finea-action-btn--secondary">
            <i class="finea-icon">arrow_back</i> Retour
        </a>
    ']
) ?>

<div class="finea-section-card">
    <form action="<?= View::url('rh/conges/nouveau') ?>" method="post" style="max-width: 600px;">
        <?= Csrf::input() ?>

        <?php if ($isHR): ?>
            <?= Form::select('employee_id', $employeeOptions, [
                'label' => 'Employé (Saisie RH)',
                'required' => true,
                'value' => (string)$actorId
            ]) ?>
        <?php else: ?>
            <input type="hidden" name="employee_id" value="<?= htmlspecialchars((string)$actorId) ?>">
        <?php endif; ?>

        <?= Form::select('leave_type_id', $typeOptions, [
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

        <div class="finea-form-group">
            <label for="reason" class="finea-label">Motif / Commentaire (Optionnel)</label>
            <textarea id="reason" name="reason" rows="3" class="finea-input"></textarea>
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" class="finea-action-btn finea-action-btn--primary">
                <i class="finea-icon">send</i> Soumettre la demande
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
