<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Security\PermissionEntityRegistry;
use App\View\Components\Form;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

$employee = isset($viewData) ? $viewData->array('employee') : ($employee ?? []);
$options = isset($viewData) ? $viewData->array('options') : ($options ?? []);
$restrictedTables = isset($viewData) ? $viewData->array('restrictedTables') : ($restrictedTables ?? []);
$employeeId = (int) ($employee['id'] ?? 0);
$isExited = !empty($employee['exit_date']);
$exitReasons = array_map(static fn(array $row): array => [
    'value' => (string) ($row['id'] ?? ''),
    'label' => (string) ($row['name'] ?? ''),
], $options['exitReasons'] ?? []);

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            $isExited ? 'Réintégration RH' : 'Sortie RH',
            ($isExited ? 'Réintégrer ' : 'Sortie de ') . (string) ($employee['full_name'] ?? ''),
            $isExited ? 'Réactiver le collaborateur dans les effectifs.' : 'Clôturer proprement le dossier RH du collaborateur.',
            Ui::button('Retour au dossier', ['href' => 'rh/personnel/' . $employeeId, 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>

        <?php if (!$isExited): ?>
            <form method="post" action="<?= View::url('rh/personnel/' . $employeeId . '/sortie') ?>" class="finea-section-card rh-operation-form">
                <?= Csrf::input() ?>
                <div class="rh-form-grid">
                    <?= Form::input('exit_date', [
                        'label' => 'Date de sortie',
                        'type' => 'date',
                        'value' => date('Y-m-d'),
                        'required' => true,
                    ]) ?>

                    <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_EXIT_REASONS])): ?>
                        <?= Form::selectSearch('exit_reason_id', array_merge(
                            [['value' => '', 'label' => 'Non renseigné']],
                            $exitReasons
                        ), '', ['label' => 'Motif']) ?>
                    <?php endif; ?>

                    <?= Form::textarea('exit_notes', [
                        'label' => 'Observations',
                        'rows' => 5,
                        'fieldClass' => 'rh-field-wide',
                    ]) ?>
                </div>
                <div class="rh-form-actions">
                    <?= Ui::button('Confirmer la sortie', ['variant' => 'danger', 'type' => 'submit']) ?>
                </div>
            </form>
        <?php else: ?>
            <form method="post" action="<?= View::url('rh/personnel/' . $employeeId . '/reintegration') ?>" class="finea-section-card rh-operation-form">
                <?= Csrf::input() ?>
                <div class="rh-form-grid">
                    <?= Form::input('start_date', [
                        'label' => 'Date de réintégration',
                        'type' => 'date',
                        'value' => date('Y-m-d'),
                        'required' => true,
                    ]) ?>
                </div>
                <div class="rh-form-actions">
                    <?= Ui::button('Réintégrer dans les effectifs', ['variant' => 'primary', 'type' => 'submit']) ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
