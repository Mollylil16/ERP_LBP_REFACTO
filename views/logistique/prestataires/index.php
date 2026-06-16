<?php
/** @var array $prestataires */
/** @var array $filters */
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne</p>
        <h1>Prestataires & Partenaires</h1>
    </div>
    <a href="<?= View::url('logistique/prestataires/nouveau') ?>" class="btn btn-primary">
        <span class="material-icons">add_business</span> Nouveau Prestataire
    </a>
</div>

<form method="GET" action="<?= View::url('logistique/prestataires') ?>" class="card" style="padding:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; gap:1rem; align-items:flex-end;">
        <div class="form-group" style="margin:0; flex:1;">
            <label>Recherche (Nom)</label>
            <input type="text" name="search" value="<?= View::e($filters['search'] ?? '') ?>" class="form-input" placeholder="Chercher un prestataire...">
        </div>
        <div class="form-group" style="margin:0; min-width:200px;">
            <label>Type de partenaire</label>
            <select name="type" class="form-select">
                <option value="">Tous les types</option>
                <option value="DOUANE" <?= ($filters['type'] ?? '') === 'DOUANE' ? 'selected' : '' ?>>Douane</option>
                <option value="COMPAGNIE_AERIENNE" <?= ($filters['type'] ?? '') === 'COMPAGNIE_AERIENNE' ? 'selected' : '' ?>>Compagnie Aérienne</option>
                <option value="COMPAGNIE_MARITIME" <?= ($filters['type'] ?? '') === 'COMPAGNIE_MARITIME' ? 'selected' : '' ?>>Compagnie Maritime</option>
                <option value="TRANSPORT_TERRESTRE" <?= ($filters['type'] ?? '') === 'TRANSPORT_TERRESTRE' ? 'selected' : '' ?>>Transport Terrestre</option>
                <option value="AUTRE" <?= ($filters['type'] ?? '') === 'AUTRE' ? 'selected' : '' ?>>Autre</option>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">Filtrer</button>
    </div>
</form>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Type</th>
                <th>Contact</th>
                <th>Téléphone</th>
                <th>Nb Factures</th>
                <th>Encours (Impayés)</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($prestataires)): ?>
            <tr><td colspan="7" class="empty-row">Aucun prestataire trouvé.</td></tr>
            <?php else: foreach ($prestataires as $p): ?>
            <tr>
                <td><strong><?= View::e($p['name']) ?></strong></td>
                <td><span class="badge badge-info"><?= View::e($p['type']) ?></span></td>
                <td>
                    <?= View::e($p['contact_name'] ?? '—') ?><br>
                    <small style="color:var(--color-muted);"><?= View::e($p['email'] ?? '—') ?></small>
                </td>
                <td><?= View::e($p['phone'] ?? '—') ?></td>
                <td><?= (int)$p['nb_factures'] ?></td>
                <td>
                    <?php if ((float)$p['encours'] > 0): ?>
                    <span style="color:#ef4444; font-weight:bold;"><?= number_format((float)$p['encours'], 0, ',', ' ') ?> XOF</span>
                    <?php else: ?>
                    <span style="color:#10b981;">0 XOF</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $p['is_active'] ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-default">Inactif</span>' ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
