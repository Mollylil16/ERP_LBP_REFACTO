<?php

use App\Helpers\View;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
require BASE_PATH . '/views/admin/_navigation.php';
$rightLabel = static function (array $permission): string {
    $rights = [];
    foreach (['view' => 'L', 'create' => 'C', 'update' => 'M', 'delete' => 'S'] as $key => $label) {
        if (!empty($permission['can_' . $key])) {
            $rights[] = $label;
        }
    }
    return implode(' ', $rights);
};
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container admin-matrix-container">
        <section class="finea-page-header admin-hero">
            <div>
                <p class="admin-eyebrow">Vue transverse</p>
                <h1>Matrice des permissions</h1>
                <p>Comparer rapidement les accès effectifs de tous les utilisateurs.</p>
            </div>
            <a class="finea-action-btn finea-action-btn--accent" href="<?= View::url('admin/users') ?>">Gérer les utilisateurs</a>
        </section>

        <section class="finea-section-card admin-matrix-card">
            <div class="admin-matrix-legend"><span><b>L</b> Lire</span><span><b>C</b> Créer</span><span><b>M</b> Modifier</span><span><b>S</b> Supprimer</span></div>
            <div class="finea-table-wrap">
                <table class="finea-table admin-matrix-table">
                    <thead><tr><th class="admin-sticky-column">Utilisateur</th><?php foreach ($entities as $entity): ?><th><span><?= View::e($entity->module) ?></span><?= View::e($entity->name) ?></th><?php endforeach; ?><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="admin-sticky-column"><strong><?= View::e($user['full_name']) ?></strong><small><?= View::e($user['email']) ?></small></td>
                            <?php foreach ($entities as $entity): ?>
                                <td>
                                    <?php if ((bool) $user['is_admin']): ?>
                                        <span class="admin-matrix-rights is-full">Tous</span>
                                    <?php else: ?>
                                        <?php $label = $rightLabel($user['permissions'][$entity->code] ?? []); ?>
                                        <span class="admin-matrix-rights <?= $label === '' ? 'is-empty' : '' ?>"><?= $label !== '' ? View::e($label) : '—' ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td><a class="admin-matrix-edit" href="<?= View::url('admin/users/' . (int) $user['id'] . '/permissions') ?>">Éditer</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
