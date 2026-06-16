<?php
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne — Prestataires</p>
        <h1>Nouveau Prestataire</h1>
    </div>
    <a href="<?= View::url('logistique/prestataires') ?>" class="btn btn-ghost">
        <span class="material-icons">arrow_back</span> Retour
    </a>
</div>

<form action="<?= View::url('logistique/prestataires') ?>" method="POST" class="card" style="max-width:800px;">
    <?= Csrf::input() ?>
    <h2 class="card-title">Informations de la structure</h2>
    <div class="form-grid-2">
        <div class="form-group" style="grid-column: span 2;">
            <label for="name">Nom de l'entreprise ou du partenaire *</label>
            <input type="text" name="name" id="name" class="form-input" required autofocus>
        </div>
        <div class="form-group">
            <label for="type">Type de partenaire *</label>
            <select name="type" id="type" class="form-select" required>
                <option value="DOUANE">Douane</option>
                <option value="COMPAGNIE_AERIENNE">Compagnie Aérienne</option>
                <option value="COMPAGNIE_MARITIME">Compagnie Maritime</option>
                <option value="TRANSPORT_TERRESTRE">Transport Terrestre</option>
                <option value="AUTRE">Autre</option>
            </select>
        </div>
        <div class="form-group">
            <label for="country">Pays d'opération</label>
            <input type="text" name="country" id="country" class="form-input" placeholder="Ex: Côte d'Ivoire, France...">
        </div>
    </div>

    <h2 class="card-title" style="margin-top:2rem;">Contact principal</h2>
    <div class="form-grid-2">
        <div class="form-group">
            <label for="contact_name">Nom du contact</label>
            <input type="text" name="contact_name" id="contact_name" class="form-input">
        </div>
        <div class="form-group">
            <label for="phone">Téléphone</label>
            <input type="tel" name="phone" id="phone" class="form-input">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" class="form-input">
        </div>
        <div class="form-group">
            <label for="contact_info">Autres informations (Adresse, IFU...)</label>
            <textarea name="contact_info" id="contact_info" class="form-textarea" rows="2"></textarea>
        </div>
    </div>

    <div class="form-actions" style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary btn-lg"><span class="material-icons">save</span> Enregistrer le prestataire</button>
    </div>
</form>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
