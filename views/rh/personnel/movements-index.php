<?php

use App\Helpers\View;

require BASE_PATH . '/views/rh/_navigation.php';
$date = static fn(string $value): string => date('d/m/Y', strtotime($value));
$labels = ['integration' => 'Entree', 'sortie' => 'Sortie', 'reintegration' => 'Reintegration'];
ob_start();
?>
<div class="finea-shell"><div class="finea-container">
    <section class="finea-page-header rh-hero">
        <div><p class="rh-eyebrow">Mouvements RH</p><h1>Entrees et sorties</h1><p>Journal consolide des integrations, sorties et reintegrations.</p></div>
        <a class="finea-action-btn finea-action-btn--accent" href="<?= View::url('rh/personnel/nouveau') ?>">Nouvelle entree</a>
    </section>
    <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>
    <section class="finea-section-card rh-recent-section">
        <?php if ($movements === []): ?>
            <div class="finea-empty-state">Aucun mouvement du personnel n'a encore ete enregistre.</div>
        <?php else: ?>
            <div class="finea-table-wrap"><table class="finea-table">
                <thead><tr><th>Date</th><th>Mouvement</th><th>Collaborateur</th><th>Titre</th><th>Details</th><th>Situation actuelle</th></tr></thead>
                <tbody><?php foreach ($movements as $movement): ?><tr>
                    <td><?= $date($movement['event_date']) ?></td>
                    <td><span class="finea-status-badge <?= $movement['event_type'] === 'sortie' ? 'finea-status-badge--warning' : 'finea-status-badge--ok' ?>"><?= View::e($labels[$movement['event_type']] ?? $movement['event_type']) ?></span></td>
                    <td><a href="<?= View::url('rh/personnel/' . (int) $movement['employee_id']) ?>"><strong><?= View::e($movement['full_name']) ?></strong></a><small class="rh-table-subtitle"><?= View::e($movement['employee_number']) ?></small></td>
                    <td><?= View::e($movement['title']) ?></td>
                    <td><?= View::e($movement['description'] ?: '') ?></td>
                    <td><?= (int) $movement['is_active'] === 1 ? 'En poste' : 'Sorti' ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div></div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
