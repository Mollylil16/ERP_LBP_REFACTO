<?php
/** @var array $agencies */
/** @var array $livreurs */
/** @var array $colisDisponibles */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage — Nouvelle Expédition</p>
        <h1>Créer un Manifeste de groupage</h1>
    </div>
    <a href="<?= View::url('colisage/expeditions') ?>" class="btn btn-ghost">
        <span class="material-icons">arrow_back</span> Retour
    </a>
</div>

<form action="<?= View::url('colisage/expeditions') ?>" method="POST">
    <?= Csrf::input() ?>

    <!-- Mode de transport & Trajet -->
    <section class="card" style="margin-bottom:1.5rem;">
        <h2 class="card-title"><span class="material-icons">local_shipping</span> Transport & Trajet</h2>
        <div class="form-grid-3">
            <div class="form-group" style="grid-column:span 1;">
                <label>Mode de transport *</label>
                <div style="display:flex; gap:.75rem; margin-top:.25rem;">
                    <?php foreach (['AERIEN' => '✈ Aérien', 'MARITIME' => '⛵ Maritime', 'TERRESTRE' => '🚚 Terrestre'] as $val => $label): ?>
                    <label class="radio-card">
                        <input type="radio" name="transport_type" value="<?= $val ?>" <?= $val === 'AERIEN' ? 'checked' : '' ?>>
                        <span><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="departure_agency_id">Agence de départ *</label>
                <select name="departure_agency_id" id="departure_agency_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($agencies as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= View::e($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="arrival_agency_id">Agence d'arrivée *</label>
                <select name="arrival_agency_id" id="arrival_agency_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($agencies as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= View::e($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="departure_date">Date de départ prévue</label>
                <input type="datetime-local" name="departure_date" id="departure_date" class="form-input">
            </div>
            <div class="form-group">
                <label for="estimated_arrival_date">Date d'arrivée estimée</label>
                <input type="datetime-local" name="estimated_arrival_date" id="estimated_arrival_date" class="form-input">
            </div>
            <?php if (!empty($livreurs)): ?>
            <div class="form-group">
                <label for="driver_user_id">Livreur / Chauffeur assigné</label>
                <select name="driver_user_id" id="driver_user_id" class="form-select">
                    <option value="">— Aucun (non terrestre) —</option>
                    <?php foreach ($livreurs as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= View::e($l['full_name']) ?> (<?= View::e($l['vehicle_model'] ?? '—') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="notes">Notes / Instructions</label>
            <textarea name="notes" id="notes" class="form-textarea" rows="2" placeholder="Ex: LTA n°XXX, vol AF547, numéro conteneur..."></textarea>
        </div>
    </section>

    <!-- Colis à regrouper -->
    <section class="card" style="margin-bottom:1.5rem;">
        <h2 class="card-title"><span class="material-icons">inventory</span> Sélection des colis</h2>
        <?php if (empty($colisDisponibles)): ?>
        <p style="color:var(--color-muted);">Aucun colis disponible (statut RÉCEPTIONNÉ et non encore affecté).</p>
        <?php else: ?>
        <p style="font-size:.85rem; color:var(--color-muted); margin-bottom:.75rem;">
            Cochez les colis à inclure dans cette expédition. Seuls les colis au statut "RÉCEPTIONNÉ" et non encore groupés sont affichés.
        </p>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" id="check-all" title="Tout sélectionner"></th>
                    <th>N° Tracking</th>
                    <th>Expéditeur</th>
                    <th>Destinataire</th>
                    <th>Départ</th>
                    <th>Arrivée</th>
                    <th>Poids</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($colisDisponibles as $c): ?>
                <tr>
                    <td><input type="checkbox" name="colis_ids[]" value="<?= $c['id'] ?>" class="colis-check"></td>
                    <td><strong><?= View::e($c['tracking_number']) ?></strong></td>
                    <td><?= View::e($c['sender_name'] ?? '—') ?></td>
                    <td><?= View::e($c['receiver_name'] ?? '—') ?></td>
                    <td><?= View::e($c['departure_agency'] ?? '—') ?></td>
                    <td><?= View::e($c['arrival_agency'] ?? '—') ?></td>
                    <td><?= number_format((float)$c['total_weight'], 2) ?> kg</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg">
            <span class="material-icons">save</span> Créer l'expédition
        </button>
        <a href="<?= View::url('colisage/expeditions') ?>" class="btn btn-ghost btn-lg">Annuler</a>
    </div>
</form>

<script>
const checkAll = document.getElementById('check-all');
if (checkAll) {
    checkAll.addEventListener('change', function() {
        document.querySelectorAll('.colis-check').forEach(c => c.checked = this.checked);
    });
}
</script>

<style>
.radio-card { display: flex; align-items: center; gap: .5rem; padding: .5rem .75rem; border: 2px solid #e5e7eb; border-radius: .5rem; cursor: pointer; font-size: .9rem; transition: border-color .2s; }
.radio-card:has(input:checked) { border-color: var(--module-accent, #0369a1); background: #eff6ff; }
.radio-card input { accent-color: var(--module-accent, #0369a1); }
</style>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
