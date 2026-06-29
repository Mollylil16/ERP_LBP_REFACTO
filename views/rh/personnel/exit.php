<?php
use App\Helpers\Csrf;
use App\Security\PermissionEntityRegistry;
use App\View\Components\Form;
use App\View\Components\Rh;
use App\View\Components\Ui;
use App\View\Pages\Rh\PersonnelExitPage;

/** @var PersonnelExitPage $page */

?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            ($page->exited ? 'Réintégrer ' : 'Sortie de ') . (string) ($page->employee['full_name'] ?? ''),
            $page->exited
                ? 'Réactiver le collaborateur dans les effectifs.'
                : 'Clôturer proprement le dossier RH du collaborateur.',
            [
                'eyebrow' => $page->exited ? 'Réintégration RH' : 'Sortie RH',
                'class' => 'rh-hero',
                'actions' => [Ui::button('Retour au dossier', [
                    'href' => 'rh/personnel/' . $page->employeeId,
                    'variant' => 'secondary',
                ])],
            ]
        ) ?>

        <?= Rh::restrictedData($page->restrictedTables) ?>

        <?php ob_start(); ?>
        <?= Csrf::input() ?>
        <div class="rh-form-grid">
            <?php if (!$page->exited): ?>
                <?= Form::input('exit_date', [
                    'label' => 'Date de sortie',
                    'type' => 'date',
                    'value' => date('Y-m-d'),
                    'required' => true,
                ]) ?>

                <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_EXIT_REASONS])): ?>
                    <?= Form::selectSearch('exit_reason_id', $page->exitReasons, '', ['label' => 'Motif']) ?>
                <?php endif; ?>

                <?= Form::textarea('exit_notes', [
                    'label' => 'Observations',
                    'rows' => 5,
                    'fieldClass' => 'rh-field-wide',
                ]) ?>
            <?php else: ?>
                <?= Form::input('start_date', [
                    'label' => 'Date de réintégration',
                    'type' => 'date',
                    'value' => date('Y-m-d'),
                    'required' => true,
                ]) ?>
            <?php endif; ?>
        </div>
        <?= Rh::formActions([
            Ui::button(
                $page->exited ? 'Réintégrer dans les effectifs' : 'Confirmer la sortie',
                [
                    'variant' => $page->exited ? 'primary' : 'danger',
                    'type' => 'submit',
                ]
            ),
        ]) ?>
        <?= Rh::form(
            'rh/personnel/' . $page->employeeId . ($page->exited ? '/reintegration' : '/sortie'),
            (string) ob_get_clean(),
            ['class' => 'finea-section-card rh-operation-form']
        ) ?>
    </div>
</div>
