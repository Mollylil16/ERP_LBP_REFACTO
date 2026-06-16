<?php
/** @var array $retraits */
/** @var array $filters */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne</p>
        <h1>Retraits Hub (Décaissements)</h1>
        <p class="subtitle">Validation et suivi des paiements aux prestataires depuis la caisse centrale.</p>
    </div>
</div>

<form method="GET" action="<?= View::url('logistique/retraits') ?>" class="card" style="padding:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; gap:1rem; align-items:flex-end;">
        <div class="form-group" style="margin:0; min-width:250px;">
            <label>Statut d'approbation</label>
            <select name="status" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="EN_ATTENTE" <?= ($filters['status'] ?? '') === 'EN_ATTENTE' ? 'selected' : '' ?>>En attente (À valider)</option>
                <option value="APPROUVE" <?= ($filters['status'] ?? '') === 'APPROUVE' ? 'selected' : '' ?>>Approuvé</option>
                <option value="REFUSE" <?= ($filters['status'] ?? '') === 'REFUSE' ? 'selected' : '' ?>>Refusé</option>
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
                <th>Prestataire / Facture</th>
                <th>Montant</th>
                <th>Référence / Transaction</th>
                <th>Initié par</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($retraits)): ?>
            <tr><td colspan="7" class="empty-row">Aucun retrait trouvé.</td></tr>
            <?php else: foreach ($retraits as $r): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($r['payment_date'])) ?></td>
                <td>
                    <strong><?= View::e($r['prestataire_name']) ?></strong><br>
                    <a href="<?= View::url('logistique/factures/' . $r['facture_id']) ?>" class="link-tracking" style="font-size:.85rem;">
                        Facture: <?= View::e($r['invoice_number']) ?>
                    </a>
                </td>
                <td><strong style="color:#10b981; font-size:1.1rem;"><?= number_format((float)$r['amount_paid'], 0, ',', ' ') ?> <?= View::e($r['currency']) ?></strong></td>
                <td><?= View::e($r['reference_transaction'] ?? '—') ?></td>
                <td><?= View::e($r['recorded_by_name'] ?? '—') ?></td>
                <td>
                    <?php if ($r['status'] === 'EN_ATTENTE'): ?>
                    <span class="badge badge-warning">En attente</span>
                    <?php elseif ($r['status'] === 'APPROUVE'): ?>
                    <span class="badge badge-success">Approuvé</span>
                    <?php elseif ($r['status'] === 'REFUSE'): ?>
                    <span class="badge badge-danger">Refusé</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['status'] === 'EN_ATTENTE'): ?>
                    <div style="display:flex; gap:.5rem;">
                        <form method="POST" action="<?= View::url('logistique/retraits/' . $r['id'] . '/approuver') ?>" style="margin:0;">
                            <?= Csrf::input() ?>
                            <button type="submit" class="btn btn-sm btn-success" title="Approuver le décaissement" onclick="return confirm('Confirmer l\'approbation et le décaissement ?')">
                                <span class="material-icons">check</span>
                            </button>
                        </form>
                        <button type="button" class="btn btn-sm btn-danger btn-refuser" data-id="<?= $r['id'] ?>" title="Refuser">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                    <?php else: ?>
                    <small style="color:var(--color-muted);">Traité par <?= View::e($r['approved_by_name'] ?? '—') ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Refus -->
<dialog id="modal-refus" class="modal">
    <div class="modal-content card" style="max-width:400px; padding:1.5rem;">
        <h3 class="card-title" style="color:#ef4444; margin-bottom:1rem;">Motif du refus</h3>
        <form method="POST" id="form-refus">
            <?= Csrf::input() ?>
            <div class="form-group">
                <label>Raison du refus (obligatoire)</label>
                <textarea name="rejection_reason" class="form-textarea" required rows="3" placeholder="Erreur montant, RIB incorrect, etc."></textarea>
            </div>
            <div class="form-actions" style="margin-top:1.5rem; justify-content:flex-end; gap:.5rem;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-refus').close()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer le refus</button>
            </div>
        </form>
    </div>
</dialog>

<script>
document.querySelectorAll('.btn-refuser').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const form = document.getElementById('form-refus');
        form.action = '<?= View::url('logistique/retraits/') ?>' + id + '/refuser';
        document.getElementById('modal-refus').showModal();
    });
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
