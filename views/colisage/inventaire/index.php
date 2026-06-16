<?php
/** @var array $inventaires */
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage</p>
        <h1>Inventaires d'entrepôt</h1>
    </div>
    <a href="<?= View::url('colisage/inventaire/nouveau') ?>" class="btn btn-primary">
        <span class="material-icons">fact_check</span> Nouvel inventaire
    </a>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Agence</th>
                <th>Statut</th>
                <th>Nb scanné</th>
                <th>Manquants</th>
                <th>Démarré le</th>
                <th>Clôturé le</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($inventaires)): ?>
            <tr><td colspan="8" class="empty-row"><span class="material-icons">inventory</span> Aucune campagne d'inventaire.</td></tr>
            <?php else: foreach ($inventaires as $inv): ?>
            <tr>
                <td><strong>#<?= $inv['id'] ?></strong></td>
                <td><?= View::e($inv['agency_name'] ?? '—') ?></td>
                <td>
                    <?php if ($inv['status'] === 'EN_COURS'): ?>
                    <span class="badge badge-warning">En cours</span>
                    <?php else: ?>
                    <span class="badge badge-success">Clôturé</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-info"><?= (int)$inv['nb_scanned'] ?></span></td>
                <td>
                    <?php if ((int)$inv['nb_missing'] > 0): ?>
                    <span class="badge badge-danger"><?= (int)$inv['nb_missing'] ?> manquant(s)</span>
                    <?php else: ?>
                    <span style="color:#10b981;">✓ Aucun</span>
                    <?php endif; ?>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($inv['started_at'])) ?></td>
                <td><?= $inv['closed_at'] ? date('d/m/Y H:i', strtotime($inv['closed_at'])) : '—' ?></td>
                <td><a href="<?= View::url('colisage/inventaire/' . $inv['id']) ?>" class="btn btn-sm btn-ghost"><span class="material-icons">visibility</span></a></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
