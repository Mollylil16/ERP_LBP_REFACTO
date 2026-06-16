<?php
/** @var array $facture */
/** @var array $retraits */
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne — Facture Prestataire</p>
        <h1 style="display:flex; align-items:center; gap:.75rem;">
            <?= View::e($facture['invoice_number']) ?>
            <?php if ($facture['status'] === 'EN_ATTENTE'): ?>
            <span class="badge badge-warning" style="font-size:.8rem;">En attente</span>
            <?php else: ?>
            <span class="badge badge-success" style="font-size:.8rem;">Payée</span>
            <?php endif; ?>
        </h1>
    </div>
    <div class="header-actions">
        <?php if ($facture['status'] === 'EN_ATTENTE'): ?>
        <a href="<?= View::url('logistique/retraits/nouveau/' . $facture['id']) ?>" class="btn btn-primary">
            <span class="material-icons">account_balance_wallet</span> Demander un décaissement (Retrait)
        </a>
        <?php endif; ?>
        <a href="<?= View::url('logistique/factures') ?>" class="btn btn-ghost">
            <span class="material-icons">arrow_back</span> Liste
        </a>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
    <!-- Infos Facture -->
    <section class="card">
        <h2 class="card-title">Détails de la Facture</h2>
        <dl class="detail-list">
            <dt>Prestataire</dt>
            <dd><strong><?= View::e($facture['prestataire_name']) ?></strong></dd>
            <dt>Numéro LTA / Dossier</dt>
            <dd><?= View::e($facture['lta_number'] ?? '—') ?></dd>
            <dt>Date d'émission</dt>
            <dd><?= $facture['issue_date'] ? date('d/m/Y', strtotime($facture['issue_date'])) : '—' ?></dd>
            <dt>Date d'échéance</dt>
            <dd><?= $facture['due_date'] ? date('d/m/Y', strtotime($facture['due_date'])) : '—' ?></dd>
            <dt>Notes</dt>
            <dd><?= View::e($facture['notes'] ?? '—') ?></dd>
        </dl>
    </section>

    <!-- Sommaire Financier -->
    <section class="card">
        <h2 class="card-title">Sommaire Financier</h2>
        <dl class="detail-list" style="font-size:1.1rem;">
            <dt>Montant Total</dt>
            <dd style="font-weight:600;"><?= number_format((float)$facture['amount'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?></dd>
            <dt>Montant déjà payé</dt>
            <dd style="color:#10b981; font-weight:600;"><?= number_format((float)$facture['amount_paid'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?></dd>
            <dt style="font-size:1.2rem; margin-top:.5rem;">Reste à Payer (Reliquat)</dt>
            <dd style="font-size:1.4rem; font-weight:800; color:#ef4444; margin-top:.5rem;">
                <?= number_format((float)$facture['reliquat'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?>
            </dd>
        </dl>
    </section>
</div>

<!-- Historique des retraits -->
<section class="card">
    <h2 class="card-title">Historique des Retraits Hub (Décaissements)</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Référence Transaction</th>
                <th>Montant</th>
                <th>Date / Enregistré par</th>
                <th>Statut / Approbation</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($retraits)): ?>
            <tr><td colspan="5" class="empty-row">Aucun retrait enregistré pour cette facture.</td></tr>
            <?php else: foreach ($retraits as $r): ?>
            <tr>
                <td><strong><?= View::e($r['reference_transaction'] ?? '—') ?></strong></td>
                <td><strong style="color:#10b981;"><?= number_format((float)$r['amount_paid'], 0, ',', ' ') ?> <?= View::e($r['currency']) ?></strong></td>
                <td>
                    <?= date('d/m/Y H:i', strtotime($r['payment_date'])) ?><br>
                    <small style="color:var(--color-muted);"><?= View::e($r['recorded_by_name'] ?? '—') ?></small>
                </td>
                <td>
                    <?php if ($r['status'] === 'EN_ATTENTE'): ?>
                    <span class="badge badge-warning">En attente d'approbation</span>
                    <?php elseif ($r['status'] === 'APPROUVE'): ?>
                    <span class="badge badge-success">Approuvé</span>
                    <small style="display:block; color:var(--color-muted); margin-top:.2rem;">le <?= date('d/m/Y H:i', strtotime($r['approved_at'])) ?></small>
                    <?php elseif ($r['status'] === 'REFUSE'): ?>
                    <span class="badge badge-danger">Refusé</span>
                    <small style="display:block; color:#ef4444; margin-top:.2rem;">Raison: <?= View::e($r['rejection_reason'] ?? '') ?></small>
                    <?php endif; ?>
                </td>
                <td><?= View::e($r['notes'] ?? '—') ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</section>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
