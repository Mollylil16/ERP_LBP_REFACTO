<?php
/** @var array $facture */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';

$reliquat = (float)$facture['reliquat'];
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne — Décaissement</p>
        <h1>Demande de retrait Hub</h1>
        <p class="subtitle">Facture <strong><?= View::e($facture['invoice_number']) ?></strong> — <?= View::e($facture['prestataire_name']) ?></p>
    </div>
    <a href="<?= View::url('logistique/factures/' . $facture['id']) ?>" class="btn btn-ghost">
        <span class="material-icons">arrow_back</span> Retour à la facture
    </a>
</div>

<div style="display:grid; grid-template-columns:1fr 2fr; gap:1.5rem;">
    <section class="card" style="background:#fef2f2; border:1px solid #fca5a5;">
        <h2 class="card-title" style="color:#b91c1c;">Situation Facture</h2>
        <dl class="detail-list" style="margin-top:1rem;">
            <dt>Montant total</dt>
            <dd><?= number_format((float)$facture['amount'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?></dd>
            <dt>Déjà payé</dt>
            <dd style="color:#10b981;"><?= number_format((float)$facture['amount_paid'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?></dd>
            <dt style="font-size:1.1rem; margin-top:.5rem;">Reste à payer</dt>
            <dd style="font-size:1.5rem; font-weight:800; color:#ef4444; margin-top:.2rem;">
                <?= number_format($reliquat, 0, ',', ' ') ?> <?= View::e($facture['currency']) ?>
            </dd>
        </dl>
    </section>

    <form action="<?= View::url('logistique/retraits') ?>" method="POST" class="card">
        <?= Csrf::input() ?>
        <input type="hidden" name="facture_id" value="<?= $facture['id'] ?>">
        
        <h2 class="card-title"><span class="material-icons">payments</span> Saisie du décaissement</h2>
        <p style="font-size:.85rem; color:var(--color-muted); margin-bottom:1.5rem;">
            Le montant demandé sera soumis à la validation de la caisse centrale. Le statut de la facture sera mis à jour automatiquement après approbation.
        </p>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="amount_paid">Montant demandé *</label>
                <div style="position:relative; display:flex; align-items:center;">
                    <input type="number" step="0.01" min="1" max="<?= $reliquat ?>" name="amount_paid" id="amount_paid" class="form-input" value="<?= $reliquat ?>" required autofocus style="padding-right:4rem; font-size:1.2rem; font-weight:bold; color:#0369a1;">
                    <span style="position:absolute; right:1rem; font-weight:bold; color:var(--color-muted);"><?= View::e($facture['currency']) ?></span>
                </div>
                <small class="form-hint">Ne peut dépasser le reste à payer.</small>
            </div>
            <div class="form-group">
                <label for="reference_transaction">Référence (Chèque, Virement...)</label>
                <input type="text" name="reference_transaction" id="reference_transaction" class="form-input" placeholder="Ex: CHQ-2023-XXXX">
            </div>
            <div class="form-group" style="grid-column:span 2;">
                <label for="notes">Justification / Notes</label>
                <textarea name="notes" id="notes" class="form-textarea" rows="2" placeholder="Ex: Paiement acompte 50%, Solde facture..."></textarea>
            </div>
        </div>

        <div class="form-actions" style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary btn-lg">
                <span class="material-icons">send</span> Soumettre la demande
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
