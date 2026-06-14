<?php

use App\Helpers\Csrf;
use App\Helpers\Auth;
use App\Helpers\View;
use App\Security\OperationPolicy;
use App\Security\PermissionAction;
use App\Security\PermissionEntityRegistry;

require BASE_PATH . '/views/rh/_navigation.php';
$date = static fn(?string $value): string => $value ? date('d/m/Y', strtotime($value)) : 'Non renseignee';
$details = [
    'Matricule' => $employee['employee_number'] ?: 'Non renseigne',
    'E-mail' => $employee['email'] ?: 'Non renseigne',
    'Telephone' => $employee['phone'] ?: 'Non renseigne',
    'Service' => $employee['service_name'],
    'Fonction' => $employee['function_name'],
    'Statut' => $employee['status_name'],
    'Site' => $employee['site'] ?: 'Non renseigne',
    'Recrutement' => $date($employee['hire_date']),
    'Prise de service' => $date($employee['start_date']),
    'CNI' => $employee['cni_number'] ?: 'Non renseigne',
    'CNPS' => $employee['cnps_number'] ?: 'Non renseigne',
    'Contact urgence' => trim(($employee['emergency_contact_name'] ?: '') . ' ' . ($employee['emergency_contact_phone'] ?: '')) ?: 'Non renseigne',
];

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header rh-hero">
            <div>
                <p class="rh-eyebrow">Dossier personnel</p>
                <h1><?= View::e($employee['full_name']) ?></h1>
                <p><?= View::e($employee['employee_number'] ?: 'Sans matricule') ?> - <?= View::e($employee['service_name']) ?></p>
            </div>
            <div class="finea-header-actions">
                <?php if (Auth::canOperation(OperationPolicy::RH_EMPLOYEE_UPDATE)): ?><a class="finea-action-btn finea-action-btn--accent" href="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/modifier') ?>">Modifier</a><?php endif; ?>
                <?php if (Auth::canOperation(OperationPolicy::RH_MUTATION_CREATE)): ?><a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/mutation') ?>">Mutation</a><?php endif; ?>
                <?php if (Auth::canOperation(OperationPolicy::RH_EXIT_MANAGE)): ?><a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/sortie') ?>">Sortie / reintegration</a><?php endif; ?>
            </div>
        </section>

        <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>

        <section class="rh-profile-summary">
            <article class="finea-section-card rh-profile-status">
                <span class="finea-status-badge <?= (int) $employee['is_active'] === 1 ? 'finea-status-badge--ok' : 'finea-status-badge--warning' ?>"><?= (int) $employee['is_active'] === 1 ? 'En poste' : 'Sorti' ?></span>
                <strong><?= View::e($employee['function_name']) ?></strong>
                <small><?= View::e($employee['status_name']) ?></small>
                <?php if ((int) $employee['is_active'] === 0): ?>
                    <p>Sortie le <?= $date($employee['exit_date']) ?><br><?= View::e($employee['exit_reason_name'] ?: '') ?></p>
                <?php endif; ?>
            </article>
            <article class="finea-section-card rh-detail-grid">
                <?php foreach ($details as $label => $detail): ?>
                    <div><small><?= View::e($label) ?></small><strong><?= View::e((string) $detail) ?></strong></div>
                <?php endforeach; ?>
            </article>
        </section>

        <div class="rh-dossier-grid">
            <?php if (Auth::can(PermissionEntityRegistry::RH_EMPLOYEE_HISTORY, PermissionAction::VIEW)): ?>
            <section class="finea-section-card">
                <h2 class="finea-section-title">Historique RH</h2>
                <?php if ($history === []): ?>
                    <div class="finea-empty-state">Aucun evenement enregistre.</div>
                <?php else: ?>
                    <div class="finea-timeline">
                        <?php foreach ($history as $event): ?>
                            <article class="finea-timeline-item">
                                <strong><?= View::e($event['title']) ?></strong>
                                <span><?= $date($event['event_date']) ?> - <?= View::e($event['event_type']) ?></span>
                                <?php if ($event['description']): ?><p><?= nl2br(View::e($event['description'])) ?></p><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (Auth::canOperation(OperationPolicy::RH_HISTORY_CREATE)): ?>
            <section class="finea-section-card">
                <h2 class="finea-section-title">Ajouter un evenement</h2>
                <form method="post" action="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/historique') ?>" class="rh-compact-form">
                    <?= Csrf::input() ?>
                    <div class="finea-field"><label>Type</label><select class="finea-select" name="event_type"><option value="note">Note RH</option><option value="promotion">Promotion</option><option value="formation">Formation</option><option value="sanction">Sanction</option><option value="renouvellement">Renouvellement</option><option value="affectation">Affectation</option></select></div>
                    <div class="finea-field"><label>Date</label><input class="finea-input" required type="date" name="event_date" value="<?= date('Y-m-d') ?>"></div>
                    <div class="finea-field"><label>Titre</label><input class="finea-input" required name="title"></div>
                    <div class="finea-field"><label>Description</label><textarea class="finea-input" rows="4" name="description"></textarea></div>
                    <button class="finea-action-btn finea-action-btn--primary">Ajouter a l'historique</button>
                </form>
            </section>
            <?php endif; ?>
        </div>

        <?php if ($mutations !== []): ?>
            <section class="finea-section-card rh-recent-section">
                <h2 class="finea-section-title">Mutations et affectations</h2>
                <div class="finea-table-wrap">
                    <table class="finea-table">
                        <thead><tr><th>Date</th><th>Service</th><th>Fonction</th><th>Statut</th><th>Site</th><th>Motif</th></tr></thead>
                        <tbody>
                        <?php foreach ($mutations as $mutation): ?>
                            <tr>
                                <td><?= $date($mutation['effective_date']) ?></td>
                                <td><?= View::e(($mutation['previous_service_name'] ?: '-') . ' -> ' . ($mutation['new_service_name'] ?: '-')) ?></td>
                                <td><?= View::e(($mutation['previous_function_name'] ?: '-') . ' -> ' . ($mutation['new_function_name'] ?: '-')) ?></td>
                                <td><?= View::e(($mutation['previous_status_name'] ?: '-') . ' -> ' . ($mutation['new_status_name'] ?: '-')) ?></td>
                                <td><?= View::e(($mutation['previous_site'] ?: '-') . ' -> ' . ($mutation['new_site'] ?: '-')) ?></td>
                                <td><?= View::e($mutation['reason'] ?: '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
