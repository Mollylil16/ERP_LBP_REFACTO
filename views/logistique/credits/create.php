<?php
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne — Crédits</p>
        <h1>Nouveau transfert / Dette inter-agences</h1>
    </div>
    <a href="<?= View::url('logistique/credits') ?>" class="btn btn-ghost">
        <span class="material-icons">arrow_back</span> Retour
    </a>
</div>

<form action="<?= View::url('logistique/credits') ?>" method="POST" class="card" style="max-width:800px;">
    <?= Csrf::input() ?>
    
    <div class="form-grid-2">
        <div class="form-group">
            <label for="from_agency_id">Agence Débitrice (Celle qui doit) *</label>
            <select name="from_agency_id" id="from_agency_id" class="form-select" required autofocus>
                <option value="">— Sélectionner —</option>
                <?php foreach ($agencies as $a): ?>
                <option value="<?= $a['id'] ?>"><?= View::e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="to_agency_id">Agence Créancière (Celle qui reçoit) *</label>
            <select name="to_agency_id" id="to_agency_id" class="form-select" required>
                <option value="">— Sélectionner —</option>
                <?php foreach ($agencies as $a): ?>
                <option value="<?= $a['id'] ?>"><?= View::e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="amount">Montant *</label>
            <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-input" required>
        </div>
        <div class="form-group">
            <label for="currency">Devise *</label>
            <select name="currency" id="currency" class="form-select" required>
                <option value="XOF">FCFA (XOF)</option>
                <option value="EUR">Euro (EUR)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="reference_colis">Référence Colis associé (Optionnel)</label>
            <input type="text" name="reference_colis" id="reference_colis" class="form-input" placeholder="Ex: LBP-2023-XXXX">
            <small class="form-hint">Si cette dette est liée à l'expédition d'un colis précis.</small>
        </div>
        <div class="form-group">
            <label for="reason">Motif / Raison de la dette *</label>
            <input type="text" name="reason" id="reason" class="form-input" required placeholder="Ex: Frais de douane avancés, transport local...">
        </div>
    </div>

    <div class="form-actions" style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary btn-lg">
            <span class="material-icons">save</span> Enregistrer la transaction
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
