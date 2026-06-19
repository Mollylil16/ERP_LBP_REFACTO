<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Rh;
use App\View\Components\Ui;
use App\View\Pages\Rh\PersonnelShowPage;

/** @var PersonnelShowPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Rh::pageHeader(
            (string) $page->employee['full_name'],
            (string) ($page->employee['employee_number'] ?: 'Sans matricule')
                . ' - ' . $page->employee['service_name'],
            [
                'eyebrow' => 'Dossier personnel',
                'actions' => Rh::actionButtons($page->headerActions),
            ]
        ) ?>

        <?= Rh::restrictedData($page->restrictedTables) ?>

        <?= Rh::profileSummary(
            $page->employee,
            $page->details,
            'Sortie le ' . View::e($page->date($page->employee['exit_date'])) . '<br>'
                . View::e($page->employee['exit_reason_name'] ?: '')
        ) ?>

        <div class="rh-dossier-grid">
            <?php if ($page->canViewHistory): ?>
                <?= Rh::card(
                    Rh::timeline($page->history, [$page, 'date']),
                    ['title' => 'Historique RH']
                ) ?>
            <?php endif; ?>

            <?php if ($page->canAddHistory): ?>
                <?php ob_start(); ?>
                <form method="post" action="<?= View::url('rh/personnel/' . $page->employeeId . '/historique') ?>" class="rh-compact-form">
                    <?= Csrf::input() ?>
                    <?= Form::selectSearch('event_type', [
                        ['value' => 'note', 'label' => 'Note RH'],
                        ['value' => 'promotion', 'label' => 'Promotion'],
                        ['value' => 'formation', 'label' => 'Formation'],
                        ['value' => 'sanction', 'label' => 'Sanction'],
                        ['value' => 'renouvellement', 'label' => 'Renouvellement'],
                        ['value' => 'affectation', 'label' => 'Affectation'],
                    ], 'note', ['label' => 'Type']) ?>
                    <?= Form::input('event_date', [
                        'label' => 'Date',
                        'type' => 'date',
                        'value' => date('Y-m-d'),
                        'required' => true,
                    ]) ?>
                    <?= Form::input('title', ['label' => 'Titre', 'required' => true]) ?>
                    <?= Form::textarea('description', ['label' => 'Description', 'rows' => 4]) ?>
                    <?= Ui::button("Ajouter à l'historique", ['variant' => 'primary', 'type' => 'submit']) ?>
                </form>
                <?= Rh::card((string) ob_get_clean(), ['title' => 'Ajouter un evenement']) ?>
            <?php endif; ?>
        </div>

        <?= Rh::card(
            Rh::documents(
                $page->documents,
                'Aucune piece jointe enregistree pour ce collaborateur.'
            ),
            [
                'class' => 'rh-recent-section',
                'eyebrow' => 'Dossier numerique',
                'title' => 'Documents joints',
                'actions' => [Ui::button('Completer le dossier', [
                    'href' => 'rh/personnel/' . $page->employeeId . '/modifier',
                    'variant' => 'secondary',
                ])],
            ]
        ) ?>

        <?php if ($page->mutations !== []): ?>
            <?= Rh::card(
                Rh::table($page->mutations, $page->mutationColumns),
                ['class' => 'rh-recent-section', 'title' => 'Mutations et affectations']
            ) ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
