<?php

/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Security\PermissionEntityRegistry;
use App\View\Components\Form;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

$employee = $viewData->array('employee');
$options = $viewData->array('options');
$restrictedTables = $viewData->array('restrictedTables');

$employeeId = (int) ($employee['id'] ?? 0);

$componentOptions = static fn(array $rows): array => array_map(static fn(array $row): array => [
    'value' => (string) ($row['id'] ?? ''),
    'label' => (string) ($row['name'] ?? ''),
], $rows);

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Mutation de ' . (string) ($employee['full_name'] ?? ''),
            "Changer l'affectation tout en conservant une trace complète.",
            [
                'eyebrow' => 'Mobilité interne',
                'class' => 'rh-hero',
                'actions' => Ui::button('Retour au dossier', [
                    'href' => 'rh/personnel/' . $employeeId,
                    'variant' => 'secondary',
                ]),
            ]
        ) ?>

        <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>

        <form method="post" action="<?= View::url('rh/personnel/' . $employeeId . '/mutation') ?>" class="finea-section-card rh-operation-form">
            <?= Csrf::input() ?>

            <div class="rh-form-grid">
                <?= Form::input('effective_date', [
                    'label' => "Date d'effet *",
                    'type' => 'date',
                    'value' => date('Y-m-d'),
                    'required' => true,
                ]) ?>

                <?= Form::input('title', [
                    'label' => 'Titre',
                    'value' => 'Mutation / affectation RH',
                ]) ?>

                <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_SERVICES])): ?>
                    <?= Form::selectSearch(
                        'service_id',
                        array_merge(
                            [['value' => '', 'label' => 'Conserver']],
                            $componentOptions($options['services'] ?? [])
                        ),
                        $employee['service_id'] ?? '',
                        ['label' => 'Nouveau service']
                    ) ?>
                <?php endif; ?>

                <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_FUNCTIONS])): ?>
                    <?= Form::selectSearch(
                        'function_id',
                        array_merge(
                            [['value' => '', 'label' => 'Conserver']],
                            $componentOptions($options['functions'] ?? [])
                        ),
                        $employee['function_id'] ?? '',
                        ['label' => 'Nouvelle fonction']
                    ) ?>
                <?php endif; ?>

                <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_STATUSES])): ?>
                    <?= Form::selectSearch(
                        'status_id',
                        array_merge(
                            [['value' => '', 'label' => 'Conserver']],
                            $componentOptions($options['statuses'] ?? [])
                        ),
                        $employee['status_id'] ?? '',
                        ['label' => 'Nouveau statut']
                    ) ?>
                <?php endif; ?>

                <?php 
                    $siteOptions = array_map(static fn($row) => ['value' => $row['name'], 'label' => $row['name']], $options['sites'] ?? []);
                ?>
                <?= Form::selectSearch(
                    'site',
                    array_merge(
                        [['value' => '', 'label' => 'Conserver']],
                        $siteOptions
                    ),
                    $employee['site'] ?? '',
                    ['label' => 'Nouveau site']
                ) ?>

                <?= Form::input('start_date', [
                    'label' => 'Nouvelle prise de service',
                    'type' => 'date',
                    'value' => (string) ($employee['start_date'] ?? ''),
                ]) ?>

                <?= Form::textarea('reason', [
                    'label' => 'Motif',
                    'rows' => 5,
                    'fieldClass' => 'rh-field-wide',
                ]) ?>
            </div>

            <div class="rh-form-actions">
                <?= Ui::button('Enregistrer la mutation', [
                    'variant' => 'primary',
                    'type' => 'submit',
                ]) ?>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
