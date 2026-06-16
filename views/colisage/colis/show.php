<?php
/** @var array $colis */
/** @var array $marchandises */
/** @var array $tracking */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';

$statusLabels = [
    'RECEPTIONNE'    => ['label' => 'Réceptionné',     'class' => 'badge-warning',   'color' => '#f59e0b'],
    'EN_PREPARATION' => ['label' => 'En préparation',  'class' => 'badge-info',      'color' => '#3b82f6'],
    'EN_TRANSIT'     => ['label' => 'En transit',      'class' => 'badge-purple',    'color' => '#8b5cf6'],
    'ARRIVE'         => ['label' => 'Arrivé',          'class' => 'badge-success',   'color' => '#10b981'],
    'RETIRE'         => ['label' => 'Retiré',          'class' => 'badge-default',   'color' => '#6b7280'],
];
$st = $statusLabels[$colis['status']] ?? ['label' => $colis['status'], 'class' => 'badge-default', 'color' => '#6b7280'];
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage — Fiche Colis</p>
        <h1 style="display:flex; align-items:center; gap:.75rem;">
            <?= View::e($colis['tracking_number']) ?>
            <span class="badge <?= $st['class'] ?>" style="font-size:.8rem;"><?= $st['label'] ?></span>
        </h1>
    </div>
    <div class="header-actions">
        <?php if ($colis['status'] === 'ARRIVE'): ?>
        <a href="<?= View::url('colisage/colis/' . $colis['id'] . '/retrait') ?>" class="btn btn-primary">
            <span class="material-icons">how_to_reg</span> Procéder au retrait
        </a>
        <?php endif; ?>
        <a href="<?= View::url('colisage/colis') ?>" class="btn btn-ghost">
            <span class="material-icons">arrow_back</span> Liste
        </a>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
    <!-- Informations colis -->
    <section class="card">
        <h2 class="card-title"><span class="material-icons">inventory_2</span> Informations</h2>
        <dl class="detail-list">
            <dt>Expéditeur</dt>
            <dd><?= View::e($colis['sender_name'] ?? '—') ?><?= $colis['sender_phone'] ? ' — ' . $colis['sender_phone'] : '' ?></dd>

            <dt>Destinataire</dt>
            <dd><?= View::e($colis['receiver_name'] ?? '—') ?><?= $colis['receiver_phone'] ? ' — ' . $colis['receiver_phone'] : '' ?></dd>

            <dt>Agence départ</dt>
            <dd><?= View::e($colis['departure_agency'] ?? '—') ?></dd>

            <dt>Agence arrivée</dt>
            <dd><?= View::e($colis['arrival_agency'] ?? '—') ?></dd>

            <dt>Poids total</dt>
            <dd><?= number_format((float)$colis['total_weight'], 2) ?> kg</dd>

            <dt>Valeur déclarée</dt>
            <dd><?= number_format((float)$colis['declared_value'], 0, ',', ' ') ?> <?= View::e($colis['currency']) ?></dd>

            <dt>Prix facturé</dt>
            <dd><?= number_format((float)$colis['total_price'], 0, ',', ' ') ?> <?= View::e($colis['currency']) ?></dd>

            <?php if ($colis['description']): ?>
            <dt>Description</dt>
            <dd><?= View::e($colis['description']) ?></dd>
            <?php endif; ?>

            <?php if ($colis['status'] === 'RETIRE'): ?>
            <dt style="color:#10b981;">Retiré par</dt>
            <dd><?= View::e($colis['retrieval_name'] ?? '—') ?> — CNI: <?= View::e($colis['retrieval_cni'] ?? '—') ?></dd>
            <dt style="color:#10b981;">Date retrait</dt>
            <dd><?= $colis['retrieved_at'] ? date('d/m/Y H:i', strtotime($colis['retrieved_at'])) : '—' ?></dd>
            <?php endif; ?>
        </dl>
    </section>

    <!-- Marchandises -->
    <section class="card">
        <h2 class="card-title"><span class="material-icons">list_alt</span> Marchandises (<?= count($marchandises) ?>)</h2>
        <?php if (empty($marchandises)): ?>
        <p style="color:var(--color-muted); font-size:.9rem;">Aucun détail de marchandise enregistré.</p>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>Description</th><th>Qté</th><th>Poids unit.</th></tr>
            </thead>
            <tbody>
                <?php foreach ($marchandises as $m): ?>
                <tr>
                    <td><?= View::e($m['description']) ?></td>
                    <td><?= $m['quantity'] ?></td>
                    <td><?= number_format((float)$m['unit_weight'], 2) ?> kg</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</div>

<!-- Tracking Timeline -->
<section class="card" style="margin-bottom:1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h2 class="card-title" style="margin:0;"><span class="material-icons">timeline</span> Historique de suivi</h2>
    </div>

    <?php if (!empty($tracking)): ?>
    <div class="tracking-timeline">
        <?php foreach (array_reverse($tracking) as $i => $event): ?>
        <div class="timeline-item <?= $i === 0 ? 'is-latest' : '' ?>">
            <div class="timeline-dot" style="background: <?= $i === 0 ? '#10b981' : '#d1d5db' ?>;"></div>
            <div class="timeline-content">
                <strong><?= View::e($event['step_name']) ?></strong>
                <span class="badge badge-sm badge-info"><?= View::e($event['status']) ?></span>
                <small style="color:var(--color-muted); display:block; margin-top:.2rem;">
                    <?= date('d/m/Y H:i', strtotime($event['recorded_at'])) ?>
                    <?= $event['recorded_by_name'] ? ' · ' . View::e($event['recorded_by_name']) : '' ?>
                    <?php if ($event['latitude'] && $event['longitude']): ?>
                    · GPS: <?= $event['latitude'] ?>, <?= $event['longitude'] ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color:var(--color-muted);">Aucun événement de suivi enregistré.</p>
    <?php endif; ?>

    <!-- Ajouter un événement -->
    <?php if ($colis['status'] !== 'RETIRE'): ?>
    <details style="margin-top:1.5rem;">
        <summary style="cursor:pointer; font-weight:600; color:var(--color-primary);">+ Ajouter une étape de tracking</summary>
        <form method="POST" action="<?= View::url('colisage/colis/' . $colis['id'] . '/tracking') ?>" style="margin-top:1rem; display:flex; gap:.75rem; flex-wrap:wrap; align-items:flex-end;">
            <?= Csrf::input() ?>
            <div class="form-group" style="flex:2; margin:0;">
                <label>Étape / Description</label>
                <input type="text" name="step_name" class="form-input" placeholder="Ex: Arrivée aéroport ABJ, Dédouanement..." required>
            </div>
            <div class="form-group" style="flex:1; margin:0; min-width:150px;">
                <label>Statut</label>
                <select name="status" class="form-select">
                    <option value="INFO">Info</option>
                    <option value="EN_TRANSIT">En transit</option>
                    <option value="ARRIVE">Arrivé</option>
                    <option value="INCIDENT">Incident</option>
                </select>
            </div>
            <div class="form-group" style="margin:0; min-width:120px;">
                <label>Latitude</label>
                <input type="text" name="latitude" class="form-input" placeholder="5.3544">
            </div>
            <div class="form-group" style="margin:0; min-width:120px;">
                <label>Longitude</label>
                <input type="text" name="longitude" class="form-input" placeholder="-4.0000">
            </div>
            <button type="submit" class="btn btn-primary" style="margin:0;">Enregistrer</button>
        </form>
    </details>
    <?php endif; ?>
</section>

<style>
.tracking-timeline { display: flex; flex-direction: column; gap: 0; }
.timeline-item { display: flex; gap: 1rem; padding: .6rem 0; position: relative; }
.timeline-item:not(:last-child)::before { content: ''; position: absolute; left: 7px; top: 22px; bottom: -4px; width: 2px; background: #e5e7eb; }
.timeline-dot { width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0; margin-top: 3px; }
.timeline-content { flex: 1; }
.timeline-item.is-latest .timeline-content strong { color: #10b981; }
</style>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
