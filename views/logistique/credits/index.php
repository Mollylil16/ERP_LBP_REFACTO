<?php
/** @var array $credits */
/** @var array $filters */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne</p>
        <h1>Crédits Inter-Agences</h1>
        <p class="subtitle">Gestion des dettes et transactions financières entre agences.</p>
    </div>
    <a href="<?= View::url('logistique/credits/nouveau') ?>" class="btn btn-primary">
        <span class="material-icons">swap_horiz</span> Nouveau transfert / Dette
    </a>
</div>

<form method="GET" action="<?= View::url('logistique/credits') ?>" class="card" style="padding:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; gap:1rem; align-items:flex-end;">
        <div class="form-group" style="margin:0; min-width:200px;">
            <label>Statut</label>
            <select name="status" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="EN_ATTENTE" <?= ($filters['status'] ?? '') === 'EN_ATTENTE' ? 'selected' : '' ?>>En attente (Non apuré)</option>
                <option value="VALIDE" <?= ($filters['status'] ?? '') === 'VALIDE' ? 'selected' : '' ?>>Validé (Apuré)</option>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">Filtrer</button>
    </div>
</form>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Agence Débitrice (De)</th>
                <th>Agence Créancière (Vers)</th>
                <th>Montant</th>
                <th>Motif / Réf. Colis</th>
                <th>Statut</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($credits)): ?>
            <tr><td colspan="7" class="empty-row">Aucun crédit inter-agences.</td></tr>
            <?php else: foreach ($credits as $c): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                <td><strong style="color:#ef4444;"><?= View::e($c['from_agency_name']) ?></strong></td>
                <td><strong style="color:#10b981;"><?= View::e($c['to_agency_name']) ?></strong></td>
                <td><strong><?= number_format((float)$c['amount'], 0, ',', ' ') ?> <?= View::e($c['currency']) ?></strong></td>
                <td>
                    <?= View::e($c['reason'] ?? '—') ?><br>
                    <?php if ($c['reference_colis']): ?>
                    <small style="color:var(--color-muted);">Réf: <?= View::e($c['reference_colis']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['status'] === 'EN_ATTENTE'): ?>
                    <span class="badge badge-warning">Non apuré</span>
                    <?php else: ?>
                    <span class="badge badge-success">Apuré</span><br>
                    <small style="color:var(--color-muted);"><?= date('d/m/y', strtotime($c['settled_at'])) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['status'] === 'EN_ATTENTE'): ?>
                    <form method="POST" action="<?= View::url('logistique/credits/' . $c['id'] . '/apurer') ?>" style="margin:0;">
                        <?= Csrf::input() ?>
                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Confirmer l\'apurement de cette dette inter-agences ?')">
                            Marquer Apuré
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
