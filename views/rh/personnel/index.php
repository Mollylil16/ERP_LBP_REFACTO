<?php

use App\Helpers\View;
use App\Helpers\Auth;
use App\Security\OperationPolicy;
use App\View\Components\Form;
use App\View\Components\Ui;

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
                <?php if (Auth::canOperation(OperationPolicy::RH_EMPLOYEE_CREATE)): ?>
                    <?= Ui::button('Intégrer un collaborateur', ['href' => 'rh/personnel/nouveau', 'variant' => 'accent']) ?>
                <?php endif; ?>
            </div>
        </section>

        <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>

        <form method="get" action="<?= View::url('rh/personnel') ?>" class="finea-filter-card rh-personnel-filters">
            <div class="finea-filter-grid">
                <?= Form::input('q', ['label' => 'Recherche', 'value' => $filters['q'] ?? '', 'placeholder' => 'Nom, matricule ou e-mail']) ?>
                <?php foreach ([['service_id', 'Service', $options['services']], ['function_id', 'Fonction', $options['functions']], ['status_id', 'Statut', $options['statuses']]] as [$name, $label, $rows]): ?>
                    <?= Form::selectSearch(
                        $name,
                        array_merge(
                            [['value' => '', 'label' => 'Tous']],
                            array_map(static fn(array $row): array => [
                                'value' => (string) ($row['id'] ?? ''),
                                'label' => (string) ($row['name'] ?? ''),
                            ], $rows)
                        ),
                        $filters[$name] ?? '',
                        ['label' => $label]
                    ) ?>
                <?php endforeach; ?>
                <?= Form::selectSearch('scope', [
                    ['value' => 'active', 'label' => 'En poste'],
                    ['value' => 'inactive', 'label' => 'Sorties'],
                    ['value' => 'all', 'label' => 'Tous'],
                ], $filters['scope'] ?? 'active', ['label' => 'Périmètre']) ?>
                <div class="finea-actions">
                    <?= Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit']) ?>
                    <?= Ui::button('Réinitialiser', ['href' => 'rh/personnel', 'variant' => 'secondary']) ?>
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
                                        <?php if (Auth::canOperation(OperationPolicy::RH_EMPLOYEE_VIEW)): ?><a href="<?= View::url('rh/personnel/' . (int) $employee['id']) ?>">Dossier</a><?php endif; ?>
                                        <?php if (Auth::canOperation(OperationPolicy::RH_EMPLOYEE_UPDATE)): ?><a href="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/modifier') ?>">Modifier</a><?php endif; ?>
                                        <?php if (Auth::canOperation(OperationPolicy::RH_MUTATION_CREATE)): ?><a href="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/mutation') ?>">Mutation</a><?php endif; ?>
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
