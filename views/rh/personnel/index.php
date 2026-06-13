<?php

use App\Helpers\View;

require BASE_PATH . '/views/rh/_navigation.php';
$items = $pagination['items'] ?? [];
$queryForPage = static function (int $page) use ($filters): string {
    return http_build_query(array_filter($filters + ['page' => $page], static fn($value) => $value !== '' && $value !== 0));
};

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header rh-hero">
            <div>
                <p class="rh-eyebrow">Annuaire RH</p>
                <h1>Liste du personnel</h1>
                <p>Rechercher, filtrer et ouvrir les dossiers individuels des collaborateurs.</p>
            </div>
            <div class="finea-header-actions">
                <span class="rh-pending-chip"><?= (int) $pagination['total'] ?> resultat<?= (int) $pagination['total'] > 1 ? 's' : '' ?></span>
                <a class="finea-action-btn finea-action-btn--accent" href="<?= View::url('rh/personnel/nouveau') ?>">Integrer un collaborateur</a>
            </div>
        </section>

        <form method="get" action="<?= View::url('rh/personnel') ?>" class="finea-filter-card rh-personnel-filters">
            <div class="finea-filter-grid">
                <div class="finea-field">
                    <label for="q">Recherche</label>
                    <input class="finea-input" id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Nom, matricule ou e-mail">
                </div>
                <?php foreach ([['service_id', 'Service', $options['services']], ['function_id', 'Fonction', $options['functions']], ['status_id', 'Statut', $options['statuses']]] as [$name, $label, $rows]): ?>
                    <div class="finea-field">
                        <label for="<?= $name ?>"><?= $label ?></label>
                        <select class="finea-select" id="<?= $name ?>" name="<?= $name ?>">
                            <option value="">Tous</option>
                            <?php foreach ($rows as $row): ?>
                                <option value="<?= (int) $row['id'] ?>" <?= (int) $filters[$name] === (int) $row['id'] ? 'selected' : '' ?>><?= View::e($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <div class="finea-field">
                    <label for="scope">Perimetre</label>
                    <select class="finea-select" id="scope" name="scope">
                        <option value="active" <?= $filters['scope'] === 'active' ? 'selected' : '' ?>>En poste</option>
                        <option value="inactive" <?= $filters['scope'] === 'inactive' ? 'selected' : '' ?>>Sorties</option>
                        <option value="all" <?= $filters['scope'] === 'all' ? 'selected' : '' ?>>Tous</option>
                    </select>
                </div>
                <div class="finea-actions">
                    <button class="finea-action-btn finea-action-btn--primary">Filtrer</button>
                    <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('rh/personnel') ?>">Reinitialiser</a>
                </div>
            </div>
        </form>

        <section class="finea-section-card">
            <?php if ($items === []): ?>
                <div class="finea-empty-state">Aucun collaborateur ne correspond aux criteres.</div>
            <?php else: ?>
                <div class="finea-table-wrap">
                    <table class="finea-table">
                        <thead>
                            <tr><th>Matricule</th><th>Collaborateur</th><th>Service</th><th>Fonction</th><th>Statut</th><th>Situation</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $employee): ?>
                            <tr>
                                <td><strong><?= View::e($employee['employee_number'] ?: 'Non renseigne') ?></strong></td>
                                <td><?= View::e($employee['full_name']) ?><small class="rh-table-subtitle"><?= View::e($employee['email'] ?: $employee['phone'] ?: '') ?></small></td>
                                <td><?= View::e($employee['service_name']) ?></td>
                                <td><?= View::e($employee['function_name']) ?></td>
                                <td><?= View::e($employee['status_name']) ?></td>
                                <td><span class="finea-status-badge <?= (int) $employee['is_active'] === 1 ? 'finea-status-badge--ok' : 'finea-status-badge--warning' ?>"><?= (int) $employee['is_active'] === 1 ? 'En poste' : 'Sorti' ?></span></td>
                                <td>
                                    <div class="rh-row-actions">
                                        <a href="<?= View::url('rh/personnel/' . (int) $employee['id']) ?>">Dossier</a>
                                        <a href="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/modifier') ?>">Modifier</a>
                                        <a href="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/mutation') ?>">Mutation</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ((int) $pagination['totalPages'] > 1): ?>
                <nav class="rh-pagination" aria-label="Pagination">
                    <?php for ($page = 1; $page <= (int) $pagination['totalPages']; $page++): ?>
                        <a class="<?= $page === (int) $pagination['page'] ? 'is-active' : '' ?>" href="<?= View::url('rh/personnel?' . $queryForPage($page)) ?>"><?= $page ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
