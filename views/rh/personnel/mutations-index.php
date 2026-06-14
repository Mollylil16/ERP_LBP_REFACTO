<?php

use App\Helpers\View;

require BASE_PATH . '/views/rh/_navigation.php';
$date = static fn(string $value): string => date('d/m/Y', strtotime($value));
ob_start();
?>
<div class="finea-shell"><div class="finea-container">
    <section class="finea-page-header rh-hero">
        <div><p class="rh-eyebrow">Mobilite interne</p><h1>Registre des mutations</h1><p>Toutes les affectations, changements de fonction, statut ou site.</p></div>
        <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('rh/personnel') ?>">Choisir un collaborateur</a>
    </section>
    <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>
    <section class="finea-section-card rh-recent-section">
        <?php if ($mutations === []): ?>
            <div class="finea-empty-state">Aucune mutation n'a encore ete enregistree.</div>
        <?php else: ?>
            <div class="finea-table-wrap"><table class="finea-table">
                <thead><tr><th>Date</th><th>Collaborateur</th><th>Service</th><th>Fonction</th><th>Statut</th><th>Site</th><th>Motif</th></tr></thead>
                <tbody><?php foreach ($mutations as $mutation): ?><tr>
                    <td><?= $date($mutation['effective_date']) ?></td>
                    <td><?php if ($mutation['employee_id']): ?><a href="<?= View::url('rh/personnel/' . (int) $mutation['employee_id']) ?>"><strong><?= View::e($mutation['full_name']) ?></strong></a><?php else: ?><strong><?= View::e($mutation['full_name']) ?></strong><?php endif; ?><small class="rh-table-subtitle"><?= View::e($mutation['employee_number']) ?></small></td>
                    <td><?= View::e(($mutation['previous_service_name'] ?: '-') . ' -> ' . ($mutation['new_service_name'] ?: '-')) ?></td>
                    <td><?= View::e(($mutation['previous_function_name'] ?: '-') . ' -> ' . ($mutation['new_function_name'] ?: '-')) ?></td>
                    <td><?= View::e(($mutation['previous_status_name'] ?: '-') . ' -> ' . ($mutation['new_status_name'] ?: '-')) ?></td>
                    <td><?= View::e(($mutation['previous_site'] ?: '-') . ' -> ' . ($mutation['new_site'] ?: '-')) ?></td>
                    <td><?= View::e($mutation['reason'] ?: '') ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div></div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
