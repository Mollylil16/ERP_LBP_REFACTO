<?php
/** @var array $factures */
/** @var array $prestataires */
/** @var array $filters */
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne</p>
        <h1>Factures Prestataires</h1>
    </div>
    <a href="<?= View::url('logistique/factures/nouvelle') ?>" class="btn btn-primary">
        <span class="material-icons">receipt</span> Saisir une facture
    </a>
</div>

<form method="GET" action="<?= View::url('logistique/factures') ?>" class="card" style="padding:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; gap:1rem; align-items:flex-end;">
        <div class="form-group" style="margin:0; min-width:200px;">
            <label>Prestataire</label>
            <select name="prestataire_id" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($prestataires as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($filters['prestataire_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= View::e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0; min-width:180px;">
            <label>Statut</label>
            <select name="status" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="EN_ATTENTE" <?= ($filters['status'] ?? '') === 'EN_ATTENTE' ? 'selected' : '' ?>>En attente (Impayé)</option>
                <option value="PAYEE" <?= ($filters['status'] ?? '') === 'PAYEE' ? 'selected' : '' ?>>Payée / Soldée</option>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">Filtrer</button>
    </div>
</form>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>N° Facture</th>
                <th>Prestataire</th>
                <th>Date d'émission</th>
                <th>Montant Total</th>
                <th>Reste à payer</th>
                <th>Statut</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($factures)): ?>
            <tr><td colspan="7" class="empty-row">Aucune facture trouvée.</td></tr>
            <?php else: foreach ($factures as $f): ?>
            <tr>
                <td><strong><?= View::e($f['invoice_number']) ?></strong></td>
                <td>
                    <?= View::e($f['prestataire_name']) ?><br>
                    <small style="color:var(--color-muted);"><?= View::e($f['prestataire_type']) ?></small>
                </td>
                <td><?= $f['issue_date'] ? date('d/m/Y', strtotime($f['issue_date'])) : '—' ?></td>
                <td><?= number_format((float)$f['amount'], 0, ',', ' ') ?> <?= View::e($f['currency']) ?></td>
                <td>
                    <?php if ((float)$f['reliquat'] > 0): ?>
                    <strong style="color:#ef4444;"><?= number_format((float)$f['reliquat'], 0, ',', ' ') ?> <?= View::e($f['currency']) ?></strong>
                    <?php else: ?>
                    <span style="color:#10b981;">0 <?= View::e($f['currency']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($f['status'] === 'EN_ATTENTE'): ?>
                    <span class="badge badge-warning">En attente</span>
                    <?php else: ?>
                    <span class="badge badge-success">Payée</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= View::url('logistique/factures/' . $f['id']) ?>" class="btn btn-sm btn-ghost"><span class="material-icons">visibility</span></a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
