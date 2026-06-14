<?php

use App\Helpers\View;

require BASE_PATH . '/views/admin/_navigation.php';
$items = $pagination['items'] ?? [];
$queryForPage = static fn(int $page): string => http_build_query(array_filter(
    $filters + ['page' => $page],
    static fn($value): bool => $value !== ''
));
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header admin-hero">
            <div>
                <p class="admin-eyebrow">Comptes et profils</p>
                <h1>Utilisateurs</h1>
                <p>Créer, retrouver et administrer les comptes de la plateforme.</p>
            </div>
            <a class="finea-action-btn finea-action-btn--accent" href="<?= View::url('admin/users/nouveau') ?>">Nouvel utilisateur</a>
        </section>

        <form class="finea-filter-card" method="get" action="<?= View::url('admin/users') ?>">
            <div class="finea-filter-grid">
                <div class="finea-field">
                    <label for="q">Recherche</label>
                    <input class="finea-input" id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Nom, email ou téléphone">
                </div>
                <div class="finea-field">
                    <label for="status">Statut</label>
                    <select class="finea-select" id="status" name="status">
                        <option value="">Tous</option>
                        <?php foreach (['active' => 'Actif', 'inactive' => 'Inactif', 'blocked' => 'Bloqué'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="finea-field">
                    <label for="profile">Profil</label>
                    <select class="finea-select" id="profile" name="profile">
                        <option value="">Tous</option>
                        <option value="admin" <?= $filters['profile'] === 'admin' ? 'selected' : '' ?>>Administrateurs</option>
                        <option value="user" <?= $filters['profile'] === 'user' ? 'selected' : '' ?>>Utilisateurs</option>
                    </select>
                </div>
                <div class="finea-actions">
                    <button class="finea-action-btn finea-action-btn--primary">Filtrer</button>
                    <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('admin/users') ?>">Réinitialiser</a>
                </div>
            </div>
        </form>

        <section class="finea-section-card">
            <div class="admin-section-heading">
                <h2 class="finea-section-title"><?= (int) $pagination['total'] ?> utilisateur<?= (int) $pagination['total'] > 1 ? 's' : '' ?></h2>
                <a href="<?= View::url('admin/permissions') ?>">Matrice des permissions</a>
            </div>
            <div class="finea-table-wrap">
                <table class="finea-table">
                    <thead><tr><th>Utilisateur</th><th>Contact</th><th>Profil</th><th>Statut</th><th>Création</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $user): ?>
                        <tr>
                            <td><strong><?= View::e($user->fullName) ?></strong><small class="admin-table-subtitle"><?= $user->rhEmployeeId ? 'Profil RH #' . (int) $user->rhEmployeeId : 'Compte système' ?></small></td>
                            <td><?= View::e($user->email) ?><small class="admin-table-subtitle"><?= View::e($user->phone ?? '') ?></small></td>
                            <td><span class="admin-profile-badge <?= $user->isAdmin ? 'is-admin' : '' ?>"><?= $user->isAdmin ? 'Administrateur' : 'Utilisateur' ?></span></td>
                            <td><span class="finea-status-badge <?= $user->status === 'active' ? 'finea-status-badge--ok' : 'finea-status-badge--warning' ?>"><?= View::e(ucfirst($user->status)) ?></span></td>
                            <td><?= View::e($user->createdAt ? date('d/m/Y', strtotime($user->createdAt)) : '—') ?></td>
                            <td><div class="admin-row-actions">
                                <a href="<?= View::url('admin/users/' . (int) $user->id) ?>">Profil</a>
                                <a href="<?= View::url('admin/users/' . (int) $user->id . '/modifier') ?>">Modifier</a>
                                <a href="<?= View::url('admin/users/' . (int) $user->id . '/permissions') ?>">Droits</a>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?><tr><td colspan="6" class="finea-empty-state">Aucun utilisateur ne correspond aux critères.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((int) $pagination['totalPages'] > 1): ?>
                <nav class="admin-pagination" aria-label="Pagination">
                    <?php for ($page = 1; $page <= (int) $pagination['totalPages']; $page++): ?>
                        <a class="<?= $page === (int) $pagination['page'] ? 'is-active' : '' ?>" href="<?= View::url('admin/users?' . $queryForPage($page)) ?>"><?= $page ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
