<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();

$employeeOptions = [];
foreach ($employees as $e) {
    $employeeOptions[$e['id']] = $e['employee_number'] . ' - ' . $e['full_name'];
}
?>

<?= Ui::pageHeader(
    'Saisie Manuelle de Pointage',
    'Enregistrer manuellement une présence pour un collaborateur.',
    ['actions' => '
        <a href="' . View::url('rh/pointage') . '" class="finea-action-btn finea-action-btn--secondary">
            <i class="finea-icon">arrow_back</i> Retour
        </a>
    ']
) ?>

<div class="finea-section-card">
    <form action="<?= View::url('rh/pointage') ?>" method="post" style="max-width: 600px;">
        <?= Csrf::input() ?>

        <?= Form::select('employee_id', $employeeOptions, [
            'label' => 'Employé',
            'required' => true
        ]) ?>

        <?= Form::input('date', [
            'label' => 'Date du pointage',
            'type' => 'date',
            'required' => true,
            'value' => date('Y-m-d')
        ]) ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <?= Form::input('check_in', [
                'label' => 'Heure d\'entrée',
                'type' => 'time',
                'value' => '08:00'
            ]) ?>
            <?= Form::input('check_out', [
                'label' => 'Heure de sortie',
                'type' => 'time',
                'value' => '18:00'
            ]) ?>
        </div>

        <?= Form::select('status', [
            'present' => 'Présent',
            'absent' => 'Absent'
        ], 'present', [
            'label' => 'Statut de présence'
        ]) ?>

        <div style="margin-top: 2rem;">
            <button type="submit" class="finea-action-btn finea-action-btn--primary">
                <i class="finea-icon">check</i> Enregistrer le pointage
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
