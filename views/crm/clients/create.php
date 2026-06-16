<?php
ob_start();
?>
<div class="finea-page-header">
    <div>
        <p class="finea-eyebrow">Référentiel CRM</p>
        <h2>Nouveau Client</h2>
        <p class="finea-subtitle">Création d'un expéditeur ou destinataire.</p>
    </div>
    <div class="finea-header-actions">
        <a href="<?= \App\Helpers\View::url('crm/clients') ?>" class="finea-action-btn finea-action-btn--secondary">
            Retour à la liste
        </a>
    </div>
</div>

<div class="finea-card">
    <form action="<?= \App\Helpers\View::url('crm/clients') ?>" method="post" class="finea-form-grid">
        <div class="finea-form-group">
            <label for="type">Type de client</label>
            <select name="type" id="type" class="finea-input" required>
                <option value="client">Client Standard (Expéditeur)</option>
                <option value="partner">Partenaire (Destinataire régulier)</option>
                <option value="prospect">Prospect</option>
            </select>
        </div>
        <div class="finea-form-group">
            <label for="name">Nom complet ou Raison Sociale *</label>
            <input type="text" name="name" id="name" class="finea-input" required>
        </div>
        <div class="finea-form-group">
            <label for="contact_name">Nom du contact</label>
            <input type="text" name="contact_name" id="contact_name" class="finea-input">
        </div>
        <div class="finea-form-group">
            <label for="phone">Téléphone</label>
            <input type="text" name="phone" id="phone" class="finea-input">
        </div>
        <div class="finea-form-group">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" class="finea-input">
        </div>
        <div class="finea-form-group">
            <label for="city">Ville</label>
            <input type="text" name="city" id="city" class="finea-input">
        </div>
        <div class="finea-form-group" style="grid-column: 1 / -1;">
            <label for="notes">Notes / Remarques</label>
            <textarea name="notes" id="notes" class="finea-input" rows="3"></textarea>
        </div>
        <div class="finea-form-actions" style="grid-column: 1 / -1; margin-top: 20px;">
            <button type="submit" class="finea-action-btn finea-action-btn--primary">Créer le client</button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/app.php';
