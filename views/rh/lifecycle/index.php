<?php

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Modal;
use App\View\Components\Rh;
use App\View\Components\Ui;
use App\View\Pages\Rh\LifecyclePage;

/** @var LifecyclePage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Processus RH, échéances et validations',
            'Contrats, périodes d’essai, missions, performances, formations et décisions hiérarchiques.',
            [
                'eyebrow' => 'Cycle de vie collaborateur',
                'class' => 'rh-hero',
                'actions' => [Ui::badge(
                    $page->pendingWorkflows() . ' validation(s) en attente',
                    'neutral',
                    ['class' => 'rh-pending-chip', 'unstyled' => true]
                )],
            ]
        ) ?>

        <?= Rh::alerts(
            array_slice($page->alerts, 0, 4),
            fn(array $alert): array => [
                'label' => (string) $alert['full_name'],
                'value' => 'J-' . max(0, (int) $alert['days_remaining']),
                'description' => (string) $alert['contract_type'] . ' · échéance '
                    . $page->date($alert['trial_end_date'] ?: $alert['end_date']),
                'tone' => 'warning',
            ]
        ) ?>

        <?= Rh::tabs($page->tabs, $page->section, [
            'class' => 'rh-lifecycle-tabs',
            'aria-label' => 'Processus RH',
        ]) ?>

        <?php if ($page->section === 'contracts'): ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                <form method="post" action="<?= View::url('rh/cycle-vie/contrats') ?>" class="rh-form-grid">
                    <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                    <?= Form::selectSearch('employee_id', array_merge(
                        [['value' => '', 'label' => 'Sélectionner']],
                        $page->employeeOptions
                    ), '', ['label' => 'Collaborateur', 'required' => true]) ?>
                    <?= Form::select('contract_type', [
                        ['value' => 'CDI', 'label' => 'CDI'],
                        ['value' => 'CDD', 'label' => 'CDD'],
                        ['value' => 'Stage', 'label' => 'Stage'],
                        ['value' => 'Consultant', 'label' => 'Consultant'],
                    ], 'CDI', ['label' => 'Type', 'required' => true]) ?>
                    <?= Form::input('reference', ['label' => 'Référence']) ?>
                    <?= Form::input('start_date', ['label' => 'Début', 'type' => 'date', 'required' => true]) ?>
                    <?= Form::input('end_date', ['label' => 'Fin', 'type' => 'date']) ?>
                    <?= Form::input('trial_start_date', ['label' => 'Début essai', 'type' => 'date']) ?>
                    <?= Form::input('trial_end_date', ['label' => 'Fin essai', 'type' => 'date']) ?>
                    <?= Rh::formActions([Ui::button('Créer et soumettre', [
                        'variant' => 'accent',
                        'type' => 'submit',
                    ])]) ?>
                </form>
                <?php $contractForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-contract-form', 'Nouveau contrat', $contractForm, 'Créer un contrat', ['eyebrow' => 'Contrats & essais']) ?>
                <?= Rh::card(
                    Rh::table($page->contracts, [
                        ['label' => 'Collaborateur', 'render' => static fn(array $row): string =>
                            '<strong>' . View::e($row['full_name']) . '</strong>'
                            . '<small class="rh-table-subtitle">'
                            . View::e($row['employee_number'] ?: 'Sans matricule') . '</small>'],
                        ['label' => 'Type', 'key' => 'contract_type'],
                        ['label' => 'Période', 'render' => static fn(array $row): string =>
                            View::e($page->date($row['start_date']) . ' → ' . $page->date($row['end_date']))],
                        ['label' => 'Essai', 'render' => static fn(array $row): string =>
                            View::e($page->date($row['trial_end_date']))],
                        ['label' => 'Statut', 'key' => 'status'],
                    ], ['empty' => 'Aucun contrat enregistré.']),
                    ['tag' => 'article', 'title' => 'Registre des contrats']
                ) ?>
            </section>
        <?php elseif ($page->section === 'assignments'): ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                <form method="post" action="<?= View::url('rh/cycle-vie/missions') ?>" class="rh-form-grid">
                    <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                    <?= Form::selectSearch('employee_id', $page->employeeOptions, '', [
                        'label' => 'Collaborateur',
                        'required' => true,
                    ]) ?>
                    <?= Form::input('title', [
                        'label' => 'Mission / projet',
                        'required' => true,
                        'fieldClass' => 'rh-field-wide',
                    ]) ?>
                    <?= Form::input('project_code', ['label' => 'Code projet']) ?>
                    <?= Form::selectSearch('manager_employee_id', array_merge(
                        [['value' => '', 'label' => 'À désigner']],
                        $page->employeeOptions
                    ), '', ['label' => 'Responsable']) ?>
                    <?= Form::selectSearch('site_id', array_merge(
                        [['value' => '', 'label' => 'Non défini']],
                        $page->siteOptions
                    ), '', ['label' => 'Site']) ?>
                    <?= Form::input('start_date', ['label' => 'Début', 'type' => 'date', 'required' => true]) ?>
                    <?= Form::input('end_date', ['label' => 'Fin', 'type' => 'date']) ?>
                    <?= Form::textarea('notes', [
                        'label' => 'Instructions',
                        'fieldClass' => 'rh-field-wide',
                    ]) ?>
                    <?= Rh::formActions([Ui::button('Soumettre l’affectation', [
                        'variant' => 'accent',
                        'type' => 'submit',
                    ])]) ?>
                </form>
                <?php $assignmentForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-assignment-form', 'Nouvelle mission ou affectation', $assignmentForm, 'Nouvelle affectation', ['eyebrow' => 'Missions']) ?>
                <?= Rh::lifecycleRecords('Historique des missions et affectations', $page->assignments, ['full_name' => 'Collaborateur', 'title' => 'Mission', 'manager_name' => 'Responsable', 'site_name' => 'Site', 'status' => 'Statut'], [$page, 'date']) ?>
            </section>
        <?php elseif ($page->section === 'evaluations'): ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                <form method="post" action="<?= View::url('rh/cycle-vie/evaluations') ?>" class="rh-form-grid">
                    <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                    <?= Form::selectSearch('employee_id', $page->employeeOptions, '', [
                        'label' => 'Collaborateur',
                        'required' => true,
                    ]) ?>
                    <?= Form::selectSearch('evaluator_employee_id', array_merge(
                        [['value' => '', 'label' => 'À désigner']],
                        $page->employeeOptions
                    ), '', ['label' => 'Évaluateur']) ?>
                    <?= Form::select('evaluation_type', [
                        ['value' => 'annual', 'label' => 'Annuelle'],
                        ['value' => 'semiannual', 'label' => 'Semestrielle'],
                        ['value' => 'trial_end', 'label' => 'Fin d’essai'],
                        ['value' => 'assignment_end', 'label' => 'Fin de mission'],
                        ['value' => 'professional', 'label' => 'Entretien professionnel'],
                    ], 'annual', ['label' => 'Type']) ?>
                    <?= Form::input('period_label', [
                        'label' => 'Période',
                        'required' => true,
                        'placeholder' => '2026 / S1',
                    ]) ?>
                    <?= Form::input('due_date', ['label' => 'Échéance', 'type' => 'date']) ?>
                    <?= Rh::formActions([Ui::button('Planifier', [
                        'variant' => 'accent',
                        'type' => 'submit',
                    ])]) ?>
                </form>
                <?php $evaluationForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-evaluation-form', 'Planifier une évaluation', $evaluationForm, 'Planifier une évaluation', ['eyebrow' => 'Performances']) ?>
                <?= Rh::lifecycleRecords('Évaluations planifiées', $page->evaluations, ['full_name' => 'Collaborateur', 'period_label' => 'Période', 'evaluation_type' => 'Type', 'due_date' => 'Échéance', 'status' => 'Statut'], [$page, 'date']) ?>
            </section>
        <?php elseif ($page->section === 'trainings'): ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                <form method="post" action="<?= View::url('rh/cycle-vie/formations') ?>" class="rh-form-grid">
                    <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                    <?= Form::input('title', [
                        'label' => 'Formation',
                        'required' => true,
                        'fieldClass' => 'rh-field-wide',
                    ]) ?>
                    <?= Form::select('training_type', [
                        ['value' => 'internal', 'label' => 'Interne'],
                        ['value' => 'external', 'label' => 'Externe'],
                        ['value' => 'mandatory', 'label' => 'Obligatoire'],
                        ['value' => 'job', 'label' => 'Métier'],
                    ], 'internal', ['label' => 'Type']) ?>
                    <?= Form::input('provider', ['label' => 'Organisme']) ?>
                    <?= Form::input('start_date', ['label' => 'Début', 'type' => 'date', 'required' => true]) ?>
                    <?= Form::input('end_date', ['label' => 'Fin', 'type' => 'date']) ?>
                    <?= Form::input('budget', ['label' => 'Budget', 'type' => 'number', 'min' => 0]) ?>
                    <?= Form::input('capacity', ['label' => 'Capacité', 'type' => 'number', 'min' => 1]) ?>
                    <?= Rh::formActions([Ui::button('Créer la session', [
                        'variant' => 'accent',
                        'type' => 'submit',
                    ])]) ?>
                </form>
                <?php $trainingForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-training-form', 'Nouvelle session de formation', $trainingForm, 'Créer une session', ['eyebrow' => 'Formation']) ?>
                <?= Rh::lifecycleRecords('Catalogue et sessions', $page->trainings, ['title' => 'Formation', 'training_type' => 'Type', 'start_date' => 'Début', 'budget' => 'Budget', 'status' => 'Statut'], [$page, 'date']) ?>
            </section>
        <?php elseif ($page->section === 'workflows'): ?>
            <?= Rh::card(
                Rh::table($page->employeeRequests, [
                    ['label' => 'Collaborateur', 'key' => 'full_name'],
                    ['label' => 'Demande', 'render' => static function (array $row): string {
                        $attachment = !empty($row['attachment_path'])
                            ? '<a class="rh-table-subtitle" href="'
                                . View::url('public/' . ltrim($row['attachment_path'], '/'))
                                . '" target="_blank" rel="noopener">Voir le justificatif</a>'
                            : '';
                        return View::e($row['request_type'])
                            . '<small class="rh-table-subtitle">' . View::e($row['reference']) . '</small>'
                            . $attachment;
                    }],
                    ['label' => 'Étape', 'render' => static fn(array $row): string =>
                        Ui::badge((string) $row['current_step'], 'info')],
                    ['label' => 'Motif', 'key' => 'reason'],
                    ['label' => 'Décision', 'render' => static fn(array $row): string =>
                        Rh::decisionForm(
                            'rh/cycle-vie/demandes-employes/' . (int) $row['id'],
                            $page->csrfToken,
                            true
                        )],
                ], ['empty' => 'Aucune demande employé en attente.']),
                ['title' => 'Demandes des collaborateurs']
            ) ?>
            <?= Rh::card(
                Rh::table($page->workflows, [
                    ['label' => 'Processus', 'key' => 'process_type'],
                    ['label' => 'Collaborateur', 'render' => static fn(array $row): string =>
                        View::e($row['full_name'] ?: 'Collectif')],
                    ['label' => 'Étape', 'render' => static fn(array $row): string =>
                        Ui::badge((string) $row['current_step'], 'info')],
                    ['label' => 'Statut', 'render' => static fn(array $row): string =>
                        Ui::badge(
                            (string) $row['status'],
                            $row['status'] === 'pending' ? 'warning' : 'success'
                        )],
                    ['label' => 'Décision', 'render' => static fn(array $row): string =>
                        $row['status'] === 'pending'
                            ? Rh::decisionForm(
                                'rh/cycle-vie/workflows/' . (int) $row['id'],
                                $page->csrfToken
                            )
                            : '—'],
                ]),
                ['title' => 'Autres workflows Manager → RH → Direction']
            ) ?>
        <?php elseif ($page->section === 'organization'): ?>
            <?php ob_start(); ?>
            <div class="rh-org-grid"><?php foreach ($page->employees as $employee): ?><article><strong><?= View::e($employee['full_name']) ?></strong><small><?= View::e($employee['employee_number'] ?: 'Collaborateur') ?></small></article><?php endforeach; ?></div>
            <?= Rh::card((string) ob_get_clean(), ['title' => 'Organigramme interactif']) ?>
        <?php elseif ($page->section === 'recruitment'): ?>
            <section class="rh-feature-grid">
                <?= Rh::card(
                    '<p>Initiation manager, validation RH et Direction, puis conversion en dossier collaborateur.</p>'
                        . Ui::button('Créer un dossier d’onboarding', ['href' => 'rh/personnel/nouveau']),
                    ['tag' => 'article', 'title' => 'Demandes de recrutement']
                ) ?>
                <?= Rh::card(
                    '<p>Pièces obligatoires, contrat, affectation, compte utilisateur et parcours d’intégration.</p>',
                    ['tag' => 'article', 'title' => 'Onboarding']
                ) ?>
                <?= Rh::card(
                    '<p>Restitution, solde, désactivation des accès, entretien de départ et archivage.</p>'
                        . Ui::button('Voir les mouvements', ['href' => 'rh/mouvements']),
                    ['tag' => 'article', 'title' => 'Offboarding']
                ) ?>
            </section>
        <?php else: ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                <form method="post" action="<?= View::url('rh/cycle-vie/discipline') ?>" class="rh-form-grid">
                    <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                    <?= Form::selectSearch('employee_id', $page->employeeOptions, '', [
                        'label' => 'Collaborateur',
                        'required' => true,
                    ]) ?>
                    <?= Form::select('action_type', [
                        ['value' => 'warning', 'label' => 'Avertissement'],
                        ['value' => 'reprimand', 'label' => 'Blâme'],
                        ['value' => 'suspension', 'label' => 'Mise à pied'],
                        ['value' => 'other', 'label' => 'Autre'],
                    ], 'warning', ['label' => 'Mesure', 'required' => true]) ?>
                    <?= Form::input('action_date', [
                        'label' => 'Date',
                        'type' => 'date',
                        'required' => true,
                    ]) ?>
                    <?= Form::textarea('reason', [
                        'label' => 'Motif',
                        'required' => true,
                        'fieldClass' => 'rh-field-wide',
                    ]) ?>
                    <?= Form::textarea('decision', [
                        'label' => 'Décision / notification',
                        'fieldClass' => 'rh-field-wide',
                    ]) ?>
                    <?= Rh::formActions([Ui::button('Enregistrer', [
                        'variant' => 'accent',
                        'type' => 'submit',
                    ])]) ?>
                </form>
                <?php $disciplineForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-discipline-form', 'Nouvelle mesure disciplinaire', $disciplineForm, 'Enregistrer une mesure', ['eyebrow' => 'Discipline']) ?>
                <?= Rh::lifecycleRecords('Registre disciplinaire sécurisé', $page->disciplinaryActions, ['full_name' => 'Collaborateur', 'action_type' => 'Mesure', 'action_date' => 'Date', 'status' => 'Statut'], [$page, 'date']) ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
