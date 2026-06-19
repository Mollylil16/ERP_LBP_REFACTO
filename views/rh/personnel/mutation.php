<?php

use App\Helpers\Csrf;
use App\Security\PermissionEntityRegistry;
use App\View\Components\Form;
use App\View\Components\Rh;
use App\View\Components\Ui;
use App\View\Pages\Rh\PersonnelMutationPage;

/** @var PersonnelMutationPage $page */

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Mutation de ' . (string) ($page->employee['full_name'] ?? ''),
            "Changer l'affectation tout en conservant une trace complète.",
            [
                'eyebrow' => 'Mobilité interne',
                'class' => 'rh-hero',
                'actions' => Ui::button('Retour au dossier', [
                    'href' => 'rh/personnel/' . $page->employeeId,
                    'variant' => 'secondary',
                ]),
            ]
        ) ?>

        <?= Rh::restrictedData($page->restrictedTables) ?>

        <?php ob_start(); ?>
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

                <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_SERVICES])): ?>
                    <?= Form::selectSearch(
                        'service_id',
                        $page->services,
                        $page->employee['service_id'] ?? '',
                        ['label' => 'Nouveau service']
                    ) ?>
                <?php endif; ?>

                <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_FUNCTIONS])): ?>
                    <?= Form::selectSearch(
                        'function_id',
                        $page->functions,
                        $page->employee['function_id'] ?? '',
                        ['label' => 'Nouvelle fonction']
                    ) ?>
                <?php endif; ?>

                <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_STATUSES])): ?>
                    <?= Form::selectSearch(
                        'status_id',
                        $page->statuses,
                        $page->employee['status_id'] ?? '',
                        ['label' => 'Nouveau statut']
                    ) ?>
                <?php endif; ?>

                <?= Form::selectSearch(
                    'site',
                    $page->sites,
                    $page->employee['site'] ?? '',
                    ['label' => 'Nouveau site']
                ) ?>

                <?= Form::input('start_date', [
                    'label' => 'Nouvelle prise de service',
                    'type' => 'date',
                    'value' => (string) ($page->employee['start_date'] ?? ''),
                ]) ?>

                <?= Form::textarea('reason', [
                    'label' => 'Motif',
                    'rows' => 5,
                    'fieldClass' => 'rh-field-wide',
                ]) ?>
            </div>

            <?= Rh::formActions([
                Ui::button('Enregistrer la mutation', [
                    'variant' => 'primary',
                    'type' => 'submit',
                ]),
            ]) ?>
        <?= Rh::form(
            'rh/personnel/' . $page->employeeId . '/mutation',
            (string) ob_get_clean(),
            ['class' => 'finea-section-card rh-operation-form']
        ) ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
