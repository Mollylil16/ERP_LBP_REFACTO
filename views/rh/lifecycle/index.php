<?php

use App\Helpers\View;
use App\View\Components\Modal;
use App\View\Components\RecordList;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';
$tabs = [
    'contracts' => 'Contrats & essais',
    'assignments' => 'Missions',
    'evaluations' => 'Évaluations',
    'trainings' => 'Formations',
    'workflows' => 'Validations',
    'organization' => 'Organigramme',
    'recruitment' => 'Recrutement',
    'discipline' => 'Discipline',
];
$date = static fn(?string $value): string => $value ? date('d/m/Y', strtotime($value)) : '—';
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header rh-hero">
            <div>
                <p class="rh-eyebrow">Cycle de vie collaborateur</p>
                <h1>Processus RH, échéances et validations</h1>
                <p>Contrats, périodes d’essai, missions, performances, formations et décisions hiérarchiques.</p>
            </div>
            <span class="rh-pending-chip"><?= count(array_filter($workflows, fn($w) => $w['status'] === 'pending')) ?> validation(s) en attente</span>
        </section>

        <?php if ($alerts !== []): ?>
            <section class="rh-alert-grid">
                <?php foreach (array_slice($alerts, 0, 4) as $alert): ?>
                    <article class="rh-alert-card tone-warning">
                        <span><?= View::e($alert['full_name']) ?></span>
                        <strong>J-<?= max(0, (int) $alert['days_remaining']) ?></strong>
                        <p><?= View::e($alert['contract_type']) ?> · échéance <?= View::e($date($alert['trial_end_date'] ?: $alert['end_date'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <nav class="rh-dashboard-tabs rh-lifecycle-tabs" aria-label="Processus RH">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="rh-dashboard-tab <?= $section === $key ? 'is-active' : '' ?>" href="<?= View::url('rh/cycle-vie?section=' . $key) ?>">
                    <strong><?= View::e($label) ?></strong>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($section === 'contracts'): ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                    <form method="post" action="<?= View::url('rh/cycle-vie/contrats') ?>" class="rh-form-grid">
                        <input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>">
                        <label class="finea-field"><span>Collaborateur *</span><select class="finea-input" name="employee_id" required><option value="">Sélectionner</option><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= View::e($e['full_name']) ?></option><?php endforeach; ?></select></label>
                        <label class="finea-field"><span>Type *</span><select class="finea-input" name="contract_type" required><option>CDI</option><option>CDD</option><option>Stage</option><option>Consultant</option></select></label>
                        <label class="finea-field"><span>Référence</span><input class="finea-input" name="reference"></label>
                        <label class="finea-field"><span>Début *</span><input class="finea-input" type="date" name="start_date" required></label>
                        <label class="finea-field"><span>Fin</span><input class="finea-input" type="date" name="end_date"></label>
                        <label class="finea-field"><span>Début essai</span><input class="finea-input" type="date" name="trial_start_date"></label>
                        <label class="finea-field"><span>Fin essai</span><input class="finea-input" type="date" name="trial_end_date"></label>
                        <div class="rh-form-actions"><button class="finea-action-btn finea-action-btn--accent">Créer et soumettre</button></div>
                    </form>
                <?php $contractForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-contract-form', 'Nouveau contrat', $contractForm, 'Créer un contrat', ['eyebrow' => 'Contrats & essais']) ?>
                <article class="finea-section-card">
                    <h2 class="finea-section-title">Registre des contrats</h2>
                    <div class="finea-table-wrap"><table class="finea-table"><thead><tr><th>Collaborateur</th><th>Type</th><th>Période</th><th>Essai</th><th>Statut</th></tr></thead><tbody>
                    <?php foreach ($contracts as $row): ?><tr><td><strong><?= View::e($row['full_name']) ?></strong><small class="rh-table-subtitle"><?= View::e($row['employee_number'] ?: 'Sans matricule') ?></small></td><td><?= View::e($row['contract_type']) ?></td><td><?= $date($row['start_date']) ?> → <?= $date($row['end_date']) ?></td><td><?= $date($row['trial_end_date']) ?></td><td><?= View::e($row['status']) ?></td></tr><?php endforeach; ?>
                    <?php if ($contracts === []): ?><tr><td colspan="5">Aucun contrat enregistré.</td></tr><?php endif; ?>
                    </tbody></table></div>
                </article>
            </section>
        <?php elseif ($section === 'assignments'): ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                    <form method="post" action="<?= View::url('rh/cycle-vie/missions') ?>" class="rh-form-grid">
                        <input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>">
                        <label class="finea-field"><span>Collaborateur *</span><select class="finea-input" name="employee_id" required><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= View::e($e['full_name']) ?></option><?php endforeach; ?></select></label>
                        <label class="finea-field rh-field-wide"><span>Mission / projet *</span><input class="finea-input" name="title" required></label>
                        <label class="finea-field"><span>Code projet</span><input class="finea-input" name="project_code"></label>
                        <label class="finea-field"><span>Responsable</span><select class="finea-input" name="manager_employee_id"><option value="">À désigner</option><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= View::e($e['full_name']) ?></option><?php endforeach; ?></select></label>
                        <label class="finea-field"><span>Site</span><select class="finea-input" name="site_id"><option value="">Non défini</option><?php foreach ($sites as $site): ?><option value="<?= (int)$site['id'] ?>"><?= View::e($site['name']) ?></option><?php endforeach; ?></select></label>
                        <label class="finea-field"><span>Début *</span><input class="finea-input" type="date" name="start_date" required></label>
                        <label class="finea-field"><span>Fin</span><input class="finea-input" type="date" name="end_date"></label>
                        <label class="finea-field rh-field-wide"><span>Instructions</span><textarea class="finea-input" name="notes"></textarea></label>
                        <div class="rh-form-actions"><button class="finea-action-btn finea-action-btn--accent">Soumettre l’affectation</button></div>
                    </form>
                <?php $assignmentForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-assignment-form', 'Nouvelle mission ou affectation', $assignmentForm, 'Nouvelle affectation', ['eyebrow' => 'Missions']) ?>
                <?= renderRhLifecycleTable('Historique des missions et affectations', $assignments, ['full_name' => 'Collaborateur', 'title' => 'Mission', 'manager_name' => 'Responsable', 'site_name' => 'Site', 'status' => 'Statut'], $date) ?>
            </section>
        <?php elseif ($section === 'evaluations'): ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                    <form method="post" action="<?= View::url('rh/cycle-vie/evaluations') ?>" class="rh-form-grid">
                        <input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>">
                        <label class="finea-field"><span>Collaborateur *</span><select class="finea-input" name="employee_id" required><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= View::e($e['full_name']) ?></option><?php endforeach; ?></select></label>
                        <label class="finea-field"><span>Évaluateur</span><select class="finea-input" name="evaluator_employee_id"><option value="">À désigner</option><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= View::e($e['full_name']) ?></option><?php endforeach; ?></select></label>
                        <label class="finea-field"><span>Type</span><select class="finea-input" name="evaluation_type"><option value="annual">Annuelle</option><option value="semiannual">Semestrielle</option><option value="trial_end">Fin d’essai</option><option value="assignment_end">Fin de mission</option><option value="professional">Entretien professionnel</option></select></label>
                        <label class="finea-field"><span>Période *</span><input class="finea-input" name="period_label" required placeholder="2026 / S1"></label>
                        <label class="finea-field"><span>Échéance</span><input class="finea-input" type="date" name="due_date"></label>
                        <div class="rh-form-actions"><button class="finea-action-btn finea-action-btn--accent">Planifier</button></div>
                    </form>
                <?php $evaluationForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-evaluation-form', 'Planifier une évaluation', $evaluationForm, 'Planifier une évaluation', ['eyebrow' => 'Performances']) ?>
                <?= renderRhLifecycleTable('Évaluations planifiées', $evaluations, ['full_name' => 'Collaborateur', 'period_label' => 'Période', 'evaluation_type' => 'Type', 'due_date' => 'Échéance', 'status' => 'Statut'], $date) ?>
            </section>
        <?php elseif ($section === 'trainings'): ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                    <form method="post" action="<?= View::url('rh/cycle-vie/formations') ?>" class="rh-form-grid">
                        <input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>">
                        <label class="finea-field rh-field-wide"><span>Formation *</span><input class="finea-input" name="title" required></label>
                        <label class="finea-field"><span>Type</span><select class="finea-input" name="training_type"><option value="internal">Interne</option><option value="external">Externe</option><option value="mandatory">Obligatoire</option><option value="job">Métier</option></select></label>
                        <label class="finea-field"><span>Organisme</span><input class="finea-input" name="provider"></label>
                        <label class="finea-field"><span>Début *</span><input class="finea-input" type="date" name="start_date" required></label>
                        <label class="finea-field"><span>Fin</span><input class="finea-input" type="date" name="end_date"></label>
                        <label class="finea-field"><span>Budget</span><input class="finea-input" type="number" min="0" name="budget"></label>
                        <label class="finea-field"><span>Capacité</span><input class="finea-input" type="number" min="1" name="capacity"></label>
                        <div class="rh-form-actions"><button class="finea-action-btn finea-action-btn--accent">Créer la session</button></div>
                    </form>
                <?php $trainingForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-training-form', 'Nouvelle session de formation', $trainingForm, 'Créer une session', ['eyebrow' => 'Formation']) ?>
                <?= renderRhLifecycleTable('Catalogue et sessions', $trainings, ['title' => 'Formation', 'training_type' => 'Type', 'start_date' => 'Début', 'budget' => 'Budget', 'status' => 'Statut'], $date) ?>
            </section>
        <?php elseif ($section === 'workflows'): ?>
            <section class="finea-section-card"><h2 class="finea-section-title">Demandes des collaborateurs</h2>
                <div class="finea-table-wrap"><table class="finea-table"><thead><tr><th>Collaborateur</th><th>Demande</th><th>Étape</th><th>Motif</th><th>Décision</th></tr></thead><tbody>
                <?php foreach ($employeeRequests as $row): ?><tr><td><?= View::e($row['full_name']) ?></td><td><?= View::e($row['request_type']) ?><small class="rh-table-subtitle"><?= View::e($row['reference']) ?></small><?php if (!empty($row['attachment_path'])): ?><a class="rh-table-subtitle" href="<?= View::url('public/' . ltrim($row['attachment_path'], '/')) ?>" target="_blank" rel="noopener">Voir le justificatif</a><?php endif; ?></td><td><?= Ui::badge((string)$row['current_step'], 'info') ?></td><td><?= View::e($row['reason']) ?></td><td><form method="post" action="<?= View::url('rh/cycle-vie/demandes-employes/' . (int)$row['id']) ?>" class="rh-row-actions"><input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>"><input class="finea-input" name="comment" placeholder="Commentaire"><button name="decision" value="approve">Valider</button><button name="decision" value="reject">Refuser</button></form></td></tr><?php endforeach; ?>
                <?php if ($employeeRequests === []): ?><tr><td colspan="5">Aucune demande employé en attente.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
            <section class="finea-section-card"><h2 class="finea-section-title">Autres workflows Manager → RH → Direction</h2>
                <div class="finea-table-wrap"><table class="finea-table"><thead><tr><th>Processus</th><th>Collaborateur</th><th>Étape</th><th>Statut</th><th>Décision</th></tr></thead><tbody>
                <?php foreach ($workflows as $row): ?><tr><td><?= View::e($row['process_type']) ?></td><td><?= View::e($row['full_name'] ?: 'Collectif') ?></td><td><?= Ui::badge((string)$row['current_step'], 'info') ?></td><td><?= Ui::badge((string)$row['status'], $row['status'] === 'pending' ? 'warning' : 'success') ?></td><td><?php if ($row['status'] === 'pending'): ?><form method="post" action="<?= View::url('rh/cycle-vie/workflows/' . (int)$row['id']) ?>" class="rh-row-actions"><input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>"><button name="decision" value="approve">Valider</button><button name="decision" value="reject">Refuser</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </section>
        <?php elseif ($section === 'organization'): ?>
            <section class="finea-section-card"><h2 class="finea-section-title">Organigramme interactif</h2><div class="rh-org-grid"><?php foreach ($employees as $employee): ?><article><strong><?= View::e($employee['full_name']) ?></strong><small><?= View::e($employee['employee_number'] ?: 'Collaborateur') ?></small></article><?php endforeach; ?></div></section>
        <?php elseif ($section === 'recruitment'): ?>
            <section class="rh-feature-grid">
                <article class="finea-section-card"><h2 class="finea-section-title">Demandes de recrutement</h2><p>Initiation manager, validation RH et Direction, puis conversion en dossier collaborateur.</p><a class="finea-action-btn" href="<?= View::url('rh/personnel/nouveau') ?>">Créer un dossier d’onboarding</a></article>
                <article class="finea-section-card"><h2 class="finea-section-title">Onboarding</h2><p>Pièces obligatoires, contrat, affectation, compte utilisateur et parcours d’intégration.</p></article>
                <article class="finea-section-card"><h2 class="finea-section-title">Offboarding</h2><p>Restitution, solde, désactivation des accès, entretien de départ et archivage.</p><a class="finea-action-btn" href="<?= View::url('rh/mouvements') ?>">Voir les mouvements</a></article>
            </section>
        <?php else: ?>
            <section class="rh-lifecycle-layout">
                <?php ob_start(); ?>
                    <form method="post" action="<?= View::url('rh/cycle-vie/discipline') ?>" class="rh-form-grid">
                        <input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>">
                        <label class="finea-field"><span>Collaborateur *</span><select class="finea-input" name="employee_id" required><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= View::e($e['full_name']) ?></option><?php endforeach; ?></select></label>
                        <label class="finea-field"><span>Mesure *</span><select class="finea-input" name="action_type"><option value="warning">Avertissement</option><option value="reprimand">Blâme</option><option value="suspension">Mise à pied</option><option value="other">Autre</option></select></label>
                        <label class="finea-field"><span>Date *</span><input class="finea-input" type="date" name="action_date" required></label>
                        <label class="finea-field rh-field-wide"><span>Motif *</span><textarea class="finea-input" name="reason" required></textarea></label>
                        <label class="finea-field rh-field-wide"><span>Décision / notification</span><textarea class="finea-input" name="decision"></textarea></label>
                        <div class="rh-form-actions"><button class="finea-action-btn finea-action-btn--accent">Enregistrer</button></div>
                    </form>
                <?php $disciplineForm = (string) ob_get_clean(); ?>
                <?= Modal::render('rh-discipline-form', 'Nouvelle mesure disciplinaire', $disciplineForm, 'Enregistrer une mesure', ['eyebrow' => 'Discipline']) ?>
                <?= renderRhLifecycleTable('Registre disciplinaire sécurisé', $disciplinaryActions, ['full_name' => 'Collaborateur', 'action_type' => 'Mesure', 'action_date' => 'Date', 'status' => 'Statut'], $date) ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';

function renderRhLifecycleTable(string $title, array $rows, array $columns, callable $date): string
{
    foreach ($rows as &$row) {
        foreach ($columns as $key => $_) {
            if (str_ends_with($key, '_date')) $row[$key] = $date($row[$key] ?? null);
        }
    }
    unset($row);
    $titleKey = (string) array_key_first($columns);
    return '<article class="finea-section-card"><h2 class="finea-section-title">' . View::e($title) . '</h2>'
        . RecordList::render($rows, $columns, [
            'title_key' => $titleKey,
            'status_key' => 'status',
            'empty' => 'Aucune donnée enregistrée.',
        ]) . '</article>';
}
