<?php
/** @var array $agencies */
/** @var array $livreurs */
/** @var array $colisDisponibles */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

$agencyOptions = array_map(fn($a) => [
    'value' => $a['id'],
    'label' => $a['name']
], $agencies);

$driverOptions = [['value' => '', 'label' => '— Aucun (non terrestre) —']];
if (!empty($livreurs)) {
    foreach ($livreurs as $l) {
        $driverOptions[] = [
            'value' => $l['id'],
            'label' => $l['full_name'] . ($l['vehicle_model'] ? ' (' . $l['vehicle_model'] . ')' : '')
        ];
    }
}
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Colisage — Nouvelle Expédition', 'Créer un Manifeste de groupage', [
            'actions' => Ui::button('Retour', 'colisage/expeditions', [
                'variant' => 'ghost'
            ])
        ]) ?>

        <form action="<?= View::url('colisage/expeditions') ?>" method="POST" style="margin-top: 1.5rem;">
            <?= Csrf::input() ?>

            <!-- Mode de transport & Trajet -->
            <div class="finea-section-card" style="margin-bottom:1.5rem;">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Transport & Trajet</h2>
                </div>
                <div class="rh-form-grid">
                    <div style="grid-column: span 3; display: flex; flex-direction: column; gap: 6px;">
                        <label style="color: #475569; font-size: .78rem; font-weight: 750;">Mode de transport *</label>
                        <div style="display:flex; gap:.75rem; margin-top:.25rem;">
                            <?php foreach (['AERIEN' => '✈ Aérien', 'MARITIME' => '⛵ Maritime', 'TERRESTRE' => '🚚 Terrestre'] as $val => $label): ?>
                            <label class="radio-card">
                                <input type="radio" name="transport_type" value="<?= $val ?>" <?= $val === 'AERIEN' ? 'checked' : '' ?>>
                                <span><?= $label ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?= Form::select('departure_agency_id', 'Agence de départ *', $agencyOptions, '', ['required' => true]) ?>
                    <?= Form::select('arrival_agency_id', 'Agence d\'arrivée *', $agencyOptions, '', ['required' => true]) ?>
                    
                    <?php if (!empty($livreurs)): ?>
                        <?= Form::select('driver_user_id', 'Livreur / Chauffeur assigné', $driverOptions) ?>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <?= Form::input('departure_date', 'Date de départ prévue', '', ['type' => 'datetime-local']) ?>
                    <?= Form::input('estimated_arrival_date', 'Date d\'arrivée estimée', '', ['type' => 'datetime-local']) ?>
                </div>
                <div style="margin-top: 1rem;">
                    <?= Form::textarea('notes', 'Notes / Instructions', '', ['rows' => 2, 'placeholder' => 'Ex: LTA n°XXX, vol AF547, numéro conteneur...']) ?>
                </div>
            </div>

            <!-- Colis à regrouper -->
            <div class="finea-section-card" style="margin-bottom:1.5rem;">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Sélection des colis</h2>
                </div>
                <?php if (empty($colisDisponibles)): ?>
                <?= Ui::emptyState('Aucun colis disponible', 'Tous les colis reçus ont déjà été affectés ou livrés.', 'inventory') ?>
                <?php else: ?>
                <p style="font-size:.85rem; color:var(--finea-muted); margin-bottom:.75rem;">
                    Cochez les colis à inclure dans cette expédition. Seuls les colis au statut "RÉCEPTIONNÉ" et non encore groupés sont affichés.
                </p>
                <div class="finea-table-wrap">
                    <table class="finea-table">
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
                </div>
                <?php endif; ?>
            </div>

            <div class="rh-form-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                <?= Ui::button('Annuler', 'colisage/expeditions', [
                    'variant' => 'ghost',
                    'class' => 'btn-lg'
                ]) ?>
                <?= Ui::button('Créer l\'expédition', null, [
                    'type' => 'submit',
                    'variant' => 'primary',
                    'class' => 'btn-lg'
                ]) ?>
            </div>
        </form>
    </div>
</div>

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
