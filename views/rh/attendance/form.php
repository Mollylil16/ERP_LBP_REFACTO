<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

$employeeOptions = array_map(static fn(array $employee): array => [
    'value' => (string) ($employee['id'] ?? ''),
    'label' => trim((string) ($employee['employee_number'] ?? '') . ' - ' . (string) ($employee['full_name'] ?? '')),
], $employees ?? []);

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Pointage',
            'Saisie manuelle',
            'Enregistrer une présence individuelle pour un collaborateur.',
            Ui::button('Retour', ['href' => 'rh/pointage', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <form action="<?= View::url('rh/pointage') ?>" method="post" class="finea-section-card rh-compact-form rh-attendance-single-form">
            <?= Csrf::input() ?>
            <?= Form::selectSearch('employee_id', $employeeOptions, '', ['label' => 'Employé', 'required' => true]) ?>
            <?= Form::input('date', ['label' => 'Date du pointage', 'type' => 'date', 'required' => true, 'value' => date('Y-m-d')]) ?>
            <div class="rh-form-grid">
                <?= Form::input('check_in', ['label' => "Heure d'arrivée", 'type' => 'time', 'value' => '08:00']) ?>
                <?= Form::input('check_out', ['label' => 'Heure de sortie', 'type' => 'time', 'value' => '17:00']) ?>
                <?= Form::select('status', [
                    ['value' => 'present', 'label' => 'Présent'],
                    ['value' => 'absent', 'label' => 'Absent'],
                ], 'present', ['label' => 'Statut']) ?>
            </div>
            <div class="rh-form-actions">
                <?= Ui::button('Enregistrer', ['variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
