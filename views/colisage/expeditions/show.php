<?php
/** @var array $expedition */
/** @var array $colis */
/** @var array $colisDisponibles */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';

$statusLabels = [
    'PLANIFIE' => ['label' => 'Planifié',   'class' => 'badge-info'],
    'EN_COURS' => ['label' => 'En cours',   'class' => 'badge-purple'],
    'ARRIVE'   => ['label' => 'Arrivé',     'class' => 'badge-success'],
    'CLOTURE'  => ['label' => 'Clôturé',    'class' => 'badge-default'],
];
$colisStatusLabels = [
    'RECEPTIONNE'    => ['label' => 'Réceptionné',    'class' => 'badge-warning'],
    'EN_PREPARATION' => ['label' => 'En préparation', 'class' => 'badge-info'],
    'EN_TRANSIT'     => ['label' => 'En transit',     'class' => 'badge-purple'],
    'ARRIVE'         => ['label' => 'Arrivé',         'class' => 'badge-success'],
    'RETIRE'         => ['label' => 'Retiré',         'class' => 'badge-default'],
];
$st = $statusLabels[$expedition['status']] ?? ['label' => $expedition['status'], 'class' => 'badge-default'];
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage — Expédition</p>
        <h1 style="display:flex; align-items:center; gap:.75rem;">
            <?= View::e($expedition['reference']) ?>
            <span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
        </h1>
    </div>
    <div class="header-actions">
        <a href="<?= View::url('colisage/expeditions') ?>" class="btn btn-ghost">
            <span class="material-icons">arrow_back</span> Liste
        </a>
    </div>
</div>

<!-- Infos expédition & Changement statut -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
    <section class="card">
        <h2 class="card-title"><span class="material-icons">info</span> Détails de l'expédition</h2>
        <dl class="detail-list">
            <dt>Type de transport</dt>
            <dd><?= View::e($expedition['transport_type']) ?></dd>
            <dt>Agence de départ</dt>
            <dd><?= View::e($expedition['departure_agency'] ?? '—') ?></dd>
            <dt>Agence d'arrivée</dt>
            <dd><?= View::e($expedition['arrival_agency'] ?? '—') ?></dd>
            <dt>Départ prévu</dt>
            <dd><?= $expedition['departure_date'] ? date('d/m/Y H:i', strtotime($expedition['departure_date'])) : '—' ?></dd>
            <dt>Arrivée estimée</dt>
            <dd><?= $expedition['estimated_arrival_date'] ? date('d/m/Y H:i', strtotime($expedition['estimated_arrival_date'])) : '—' ?></dd>
            <?php if ($expedition['notes']): ?>
            <dt>Notes</dt>
            <dd><?= View::e($expedition['notes']) ?></dd>
            <?php endif; ?>
        </dl>
    </section>

    <!-- Changement de statut -->
    <?php if ($expedition['status'] !== 'CLOTURE'): ?>
    <section class="card">
        <h2 class="card-title"><span class="material-icons">update</span> Changer le statut</h2>
        <form method="POST" action="<?= View::url('colisage/expeditions/' . $expedition['id'] . '/statut') ?>">
            <?= Csrf::input() ?>
            <div class="form-group">
                <label>Nouveau statut</label>
                <select name="status" class="form-select">
                    <option value="PLANIFIE" <?= $expedition['status'] === 'PLANIFIE' ? 'selected' : '' ?>>Planifié</option>
                    <option value="EN_COURS" <?= $expedition['status'] === 'EN_COURS' ? 'selected' : '' ?>>En cours (Départ)</option>
                    <option value="ARRIVE" <?= $expedition['status'] === 'ARRIVE' ? 'selected' : '' ?>>Arrivé</option>
                    <option value="CLOTURE">Clôturer</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Valider le changement</button>
        </form>
    </section>
    <?php else: ?>
    <section class="card" style="border:2px solid #10b981;">
        <h2 class="card-title" style="color:#065f46;"><span class="material-icons" style="color:#10b981;">check_circle</span> Expédition clôturée</h2>
        <p style="font-size:.9rem; color:#047857;">Cette expédition est terminée et ne peut plus être modifiée.</p>
    </section>
    <?php endif; ?>
</div>

<!-- Colis de l'expédition -->
<section class="card" style="margin-bottom:1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h2 class="card-title" style="margin:0;">
            <span class="material-icons">inventory</span> Colis de cette expédition (<?= count($colis) ?>)
        </h2>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>N° Tracking</th>
                <th>Expéditeur</th>
                <th>Destinataire</th>
                <th>Poids</th>
                <th>Statut</th>
                <th>Ajouté le</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($colis)): ?>
            <tr><td colspan="6" class="empty-row">Aucun colis dans cette expédition.</td></tr>
            <?php else: foreach ($colis as $c): ?>
            <tr>
                <td><a href="<?= View::url('colisage/colis/' . $c['id']) ?>" class="link-tracking"><strong><?= View::e($c['tracking_number']) ?></strong></a></td>
                <td><?= View::e($c['sender_name'] ?? '—') ?></td>
                <td><?= View::e($c['receiver_name'] ?? '—') ?></td>
                <td><?= number_format((float)$c['total_weight'], 2) ?> kg</td>
                <td>
                    <?php $cs = $colisStatusLabels[$c['status']] ?? ['label' => $c['status'], 'class' => 'badge-default']; ?>
                    <span class="badge <?= $cs['class'] ?>"><?= $cs['label'] ?></span>
                </td>
                <td><?= $c['added_at'] ? date('d/m/Y H:i', strtotime($c['added_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</section>

<!-- Ajouter un colis à l'expédition -->
<?php if ($expedition['status'] === 'PLANIFIE' && !empty($colisDisponibles)): ?>
<section class="card">
    <h2 class="card-title"><span class="material-icons">add_box</span> Ajouter un colis</h2>
    <form method="POST" action="<?= View::url('colisage/expeditions/' . $expedition['id'] . '/ajouter-colis') ?>" style="display:flex; gap:1rem; align-items:flex-end;">
        <?= Csrf::input() ?>
        <div class="form-group" style="flex:1; margin:0;">
            <label>Colis disponible</label>
            <select name="colis_id" class="form-select" required>
                <option value="">— Sélectionner —</option>
                <?php foreach ($colisDisponibles as $c): ?>
                <option value="<?= $c['id'] ?>"><?= View::e($c['tracking_number']) ?> — <?= View::e($c['sender_name'] ?? '') ?> → <?= View::e($c['receiver_name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="margin:0;">Ajouter</button>
    </form>
</section>
<?php endif; ?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
