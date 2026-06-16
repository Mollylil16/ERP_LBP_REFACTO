<?php
/** @var array $prestataires */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne — Factures</p>
        <h1>Nouvelle Facture</h1>
    </div>
    <a href="<?= View::url('logistique/factures') ?>" class="btn btn-ghost">
        <span class="material-icons">arrow_back</span> Retour
    </a>
</div>

<form action="<?= View::url('logistique/factures') ?>" method="POST" class="card" style="max-width:800px;">
    <?= Csrf::input() ?>
    <h2 class="card-title">Détails de la facture</h2>
    <div class="form-grid-2">
        <div class="form-group" style="grid-column: span 2;">
            <label for="prestataire_id">Prestataire *</label>
            <select name="prestataire_id" id="prestataire_id" class="form-select" required autofocus>
                <option value="">— Sélectionner le prestataire —</option>
                <?php foreach ($prestataires as $p): ?>
                <option value="<?= $p['id'] ?>"><?= View::e($p['name']) ?> (<?= View::e($p['type']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="invoice_number">Numéro de facture (référence) *</label>
            <input type="text" name="invoice_number" id="invoice_number" class="form-input" required>
        </div>
        <div class="form-group">
            <label for="lta_number">N° LTA ou Dossier (Optionnel)</label>
            <input type="text" name="lta_number" id="lta_number" class="form-input" placeholder="Pour relier à un groupage">
        </div>
        <div class="form-group">
            <label for="amount">Montant total *</label>
            <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-input" required>
        </div>
        <div class="form-group">
            <label for="currency">Devise *</label>
            <select name="currency" id="currency" class="form-select" required>
                <option value="XOF">FCFA (XOF)</option>
                <option value="EUR">Euro (EUR)</option>
                <option value="USD">Dollar (USD)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="issue_date">Date d'émission</label>
            <input type="date" name="issue_date" id="issue_date" class="form-input">
        </div>
        <div class="form-group">
            <label for="due_date">Date d'échéance / Date limite de paiement</label>
            <input type="date" name="due_date" id="due_date" class="form-input">
        </div>
        <div class="form-group" style="grid-column: span 2;">
            <label for="notes">Notes / Description des prestations</label>
            <textarea name="notes" id="notes" class="form-textarea" rows="3"></textarea>
        </div>
    </div>

    <div class="form-actions" style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary btn-lg"><span class="material-icons">save</span> Enregistrer la facture</button>
    </div>
</form>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
