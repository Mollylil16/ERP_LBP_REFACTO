<?php
/** @var array $clients */
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage</p>
        <h1>Nouveau Colis</h1>
        <p class="subtitle">Enregistrement d'une nouvelle réception de marchandise.</p>
    </div>
    <div class="header-actions">
        <a href="<?= View::url('colisage/colis') ?>" class="btn btn-ghost">
            <span class="material-icons">arrow_back</span> Retour à la liste
        </a>
    </div>
</div>

<form action="<?= View::url('colisage/colis') ?>" method="POST" id="form-colis">
    <?= Csrf::input() ?>

    <!-- Section : Clients -->
    <section class="card" style="margin-bottom:1.5rem;">
        <h2 class="card-title">
            <span class="material-icons">people</span> Expéditeur & Destinataire
        </h2>
        <div class="form-grid-2">
            <div class="form-group">
                <label for="sender_id">Expéditeur *</label>
                <select name="sender_id" id="sender_id" class="form-select" required>
                    <option value="">— Sélectionner un expéditeur —</option>
                    <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= View::e($c['name']) ?><?= $c['phone'] ? ' — ' . $c['phone'] : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint">
                    Client inexistant ? <a href="<?= View::url('crm/clients/nouveau') ?>" target="_blank">Créer un client</a>
                </small>
            </div>
            <div class="form-group">
                <label for="receiver_id">Destinataire *</label>
                <select name="receiver_id" id="receiver_id" class="form-select" required>
                    <option value="">— Sélectionner un destinataire —</option>
                    <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= View::e($c['name']) ?><?= $c['phone'] ? ' — ' . $c['phone'] : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <!-- Section : Trajet -->
    <section class="card" style="margin-bottom:1.5rem;">
        <h2 class="card-title">
            <span class="material-icons">swap_horiz</span> Trajet
        </h2>
        <div class="form-grid-2">
            <div class="form-group">
                <label for="departure_agency_id">Agence de départ *</label>
                <select name="departure_agency_id" id="departure_agency_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($agencies as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= View::e($a['name']) ?> (<?= View::e($a['country'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="arrival_agency_id">Agence d'arrivée *</label>
                <select name="arrival_agency_id" id="arrival_agency_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($agencies as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= View::e($a['name']) ?> (<?= View::e($a['country'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <!-- Section : Informations colis -->
    <section class="card" style="margin-bottom:1.5rem;">
        <h2 class="card-title">
            <span class="material-icons">inventory_2</span> Informations colis
        </h2>
        <div class="form-grid-3">
            <div class="form-group">
                <label for="total_weight">Poids total (kg)</label>
                <input type="number" step="0.01" min="0" name="total_weight" id="total_weight" class="form-input" value="0.00">
            </div>
            <div class="form-group">
                <label for="declared_value">Valeur déclarée (douane)</label>
                <input type="number" step="0.01" min="0" name="declared_value" id="declared_value" class="form-input" value="0.00">
            </div>
            <div class="form-group">
                <label for="total_price">Prix facturé</label>
                <input type="number" step="0.01" min="0" name="total_price" id="total_price" class="form-input" value="0.00">
            </div>
            <div class="form-group">
                <label for="currency">Devise</label>
                <select name="currency" id="currency" class="form-select">
                    <option value="XOF">FCFA (XOF)</option>
                    <option value="EUR">Euros (EUR)</option>
                </select>
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label for="description">Description courte du colis</label>
                <input type="text" name="description" id="description" class="form-input" placeholder="Ex: Vêtements, électronique, alimentaire...">
            </div>
        </div>
        <div class="form-group">
            <label for="notes">Notes internes</label>
            <textarea name="notes" id="notes" class="form-textarea" rows="2" placeholder="Remarques, instructions particulières..."></textarea>
        </div>
    </section>

    <!-- Section : Marchandises -->
    <section class="card" style="margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h2 class="card-title" style="margin:0;">
                <span class="material-icons">list_alt</span> Détail des marchandises
            </h2>
            <button type="button" class="btn btn-outline btn-sm" id="btn-add-ligne">
                <span class="material-icons">add</span> Ajouter une ligne
            </button>
        </div>
        <table class="data-table" id="table-marchandises">
            <thead>
                <tr>
                    <th>Description de la marchandise *</th>
                    <th style="width:120px;">Quantité</th>
                    <th style="width:140px;">Poids unitaire (kg)</th>
                    <th style="width:50px;"></th>
                </tr>
            </thead>
            <tbody id="tbody-marchandises">
                <tr class="ligne-marchandise">
                    <td><input type="text" name="marchandise_description[]" class="form-input" placeholder="Ex: Chaussures de sport..." required></td>
                    <td><input type="number" name="marchandise_quantity[]" class="form-input" value="1" min="1"></td>
                    <td><input type="number" name="marchandise_weight[]" class="form-input" value="0" step="0.01" min="0"></td>
                    <td><button type="button" class="btn btn-sm btn-ghost btn-delete-ligne" title="Supprimer"><span class="material-icons">delete_outline</span></button></td>
                </tr>
            </tbody>
        </table>
    </section>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg">
            <span class="material-icons">save</span> Créer le colis & générer le tracking
        </button>
        <a href="<?= View::url('colisage/colis') ?>" class="btn btn-ghost btn-lg">Annuler</a>
    </div>
</form>

<script>
document.getElementById('btn-add-ligne').addEventListener('click', function() {
    const tbody = document.getElementById('tbody-marchandises');
    const tmpl = tbody.querySelector('.ligne-marchandise').cloneNode(true);
    tmpl.querySelectorAll('input').forEach(i => {
        if (i.type === 'number') i.value = i.name.includes('quantity') ? '1' : '0';
        else i.value = '';
    });
    tbody.appendChild(tmpl);
});

document.getElementById('tbody-marchandises').addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-delete-ligne');
    if (!btn) return;
    const rows = document.querySelectorAll('.ligne-marchandise');
    if (rows.length > 1) btn.closest('tr').remove();
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
