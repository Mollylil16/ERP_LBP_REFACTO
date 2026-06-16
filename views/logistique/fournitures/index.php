<?php
/** @var array $fournitures */
/** @var array $filters */
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne</p>
        <h1>Fournitures & Consommables</h1>
        <p class="subtitle">Gestion des demandes de fournitures pour les différentes agences.</p>
    </div>
    <a href="<?= View::url('logistique/fournitures/nouvelle') ?>" class="btn btn-primary">
        <span class="material-icons">add_shopping_cart</span> Nouvelle demande
    </a>
</div>

<form method="GET" action="<?= View::url('logistique/fournitures') ?>" class="card" style="padding:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; gap:1rem; align-items:flex-end;">
        <div class="form-group" style="margin:0; min-width:200px;">
            <label>Agence</label>
            <select name="agency_id" class="form-select">
                <option value="">Toutes</option>
                <?php foreach ($agencies as $a): ?>
                <option value="<?= $a['id'] ?>" <?= ($filters['agency_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= View::e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0; min-width:200px;">
            <label>Statut</label>
            <select name="status" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="EN_ATTENTE" <?= ($filters['status'] ?? '') === 'EN_ATTENTE' ? 'selected' : '' ?>>En attente</option>
                <option value="APPROUVEE" <?= ($filters['status'] ?? '') === 'APPROUVEE' ? 'selected' : '' ?>>Approuvée (à livrer)</option>
                <option value="LIVREE" <?= ($filters['status'] ?? '') === 'LIVREE' ? 'selected' : '' ?>>Livrée</option>
                <option value="REJETEE" <?= ($filters['status'] ?? '') === 'REJETEE' ? 'selected' : '' ?>>Rejetée</option>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">Filtrer</button>
    </div>
</form>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Date demande</th>
                <th>Agence</th>
                <th>Demandeur</th>
                <th>Articles demandés</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($fournitures)): ?>
            <tr><td colspan="6" class="empty-row">Aucune demande de fourniture.</td></tr>
            <?php else: foreach ($fournitures as $f): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($f['created_at'])) ?></td>
                <td><strong><?= View::e($f['agency_name']) ?></strong></td>
                <td><?= View::e($f['requested_by_name'] ?? '—') ?></td>
                <td><pre style="margin:0; font-family:inherit; font-size:.85rem; max-width:300px; white-space:pre-wrap;"><?= View::e($f['items_requested']) ?></pre></td>
                <td>
                    <?php if ($f['status'] === 'EN_ATTENTE'): ?>
                    <span class="badge badge-warning">En attente</span>
                    <?php elseif ($f['status'] === 'APPROUVEE'): ?>
                    <span class="badge badge-info">Approuvée</span>
                    <?php elseif ($f['status'] === 'LIVREE'): ?>
                    <span class="badge badge-success">Livrée</span><br>
                    <small style="color:var(--color-muted);">le <?= date('d/m/y', strtotime($f['delivered_at'])) ?></small>
                    <?php elseif ($f['status'] === 'REJETEE'): ?>
                    <span class="badge badge-danger" title="<?= View::e($f['rejection_reason']) ?>">Rejetée</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($f['status'] === 'EN_ATTENTE'): ?>
                    <div style="display:flex; gap:.5rem;">
                        <form method="POST" action="<?= View::url('logistique/fournitures/' . $f['id'] . '/valider') ?>" style="margin:0;">
                            <?= Csrf::input() ?>
                            <button type="submit" class="btn btn-sm btn-success" title="Approuver"><span class="material-icons">check</span></button>
                        </form>
                        <form method="POST" action="<?= View::url('logistique/fournitures/' . $f['id'] . '/rejeter') ?>" style="margin:0; display:inline-flex; align-items:center;">
                            <?= Csrf::input() ?>
                            <!-- Un vrai modal serait mieux ici pour la raison de rejet, on simule avec un prompt JS basique -->
                            <input type="hidden" name="rejection_reason" class="reason-input-<?= $f['id'] ?>">
                            <button type="button" class="btn btn-sm btn-danger btn-rejeter" data-id="<?= $f['id'] ?>" title="Rejeter"><span class="material-icons">close</span></button>
                        </form>
                    </div>
                    <?php elseif ($f['status'] === 'APPROUVEE'): ?>
                    <form method="POST" action="<?= View::url('logistique/fournitures/' . $f['id'] . '/livrer') ?>" style="margin:0;">
                        <?= Csrf::input() ?>
                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Confirmer la livraison de ces fournitures à l\'agence ?')">
                            Marquer Livrée
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.btn-rejeter').forEach(btn => {
    btn.addEventListener('click', function() {
        const reason = prompt("Motif du rejet de la demande de fourniture :");
        if (reason) {
            const id = this.getAttribute('data-id');
            document.querySelector('.reason-input-' + id).value = reason;
            this.closest('form').submit();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
