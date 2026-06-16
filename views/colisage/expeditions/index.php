<?php
/** @var array $expeditions */
/** @var array $filters */
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';

$statusLabels = [
    'PLANIFIE' => ['label' => 'Planifié',   'class' => 'badge-info'],
    'EN_COURS' => ['label' => 'En cours',   'class' => 'badge-purple'],
    'ARRIVE'   => ['label' => 'Arrivé',     'class' => 'badge-success'],
    'CLOTURE'  => ['label' => 'Clôturé',    'class' => 'badge-default'],
];
$transportIcons = ['AERIEN' => 'flight', 'MARITIME' => 'directions_boat', 'TERRESTRE' => 'local_shipping'];
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage</p>
        <h1>Expéditions & Manifestes</h1>
    </div>
    <a href="<?= View::url('colisage/expeditions/nouveau') ?>" class="btn btn-primary">
        <span class="material-icons">add</span> Nouvelle expédition
    </a>
</div>

<!-- Filtres -->
<form method="GET" action="<?= View::url('colisage/expeditions') ?>" class="card" style="padding:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0; min-width:150px;">
            <label>Statut</label>
            <select name="status" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($statusLabels as $val => $info): ?>
                <option value="<?= $val ?>" <?= ($filters['status'] ?? '') === $val ? 'selected' : '' ?>><?= $info['label'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0; min-width:160px;">
            <label>Mode de transport</label>
            <select name="transport_type" class="form-select">
                <option value="">Tous</option>
                <option value="AERIEN" <?= ($filters['transport_type'] ?? '') === 'AERIEN' ? 'selected' : '' ?>>Aérien</option>
                <option value="MARITIME" <?= ($filters['transport_type'] ?? '') === 'MARITIME' ? 'selected' : '' ?>>Maritime</option>
                <option value="TERRESTRE" <?= ($filters['transport_type'] ?? '') === 'TERRESTRE' ? 'selected' : '' ?>>Terrestre</option>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">
            <span class="material-icons">search</span> Filtrer
        </button>
    </div>
</form>

<!-- Table expéditions -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Référence</th>
                <th>Mode</th>
                <th>Trajet</th>
                <th>Nb colis</th>
                <th>Départ</th>
                <th>Arrivée estimée</th>
                <th>Statut</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($expeditions)): ?>
            <tr><td colspan="8" class="empty-row"><span class="material-icons">local_shipping</span> Aucune expédition.</td></tr>
            <?php else: foreach ($expeditions as $e): ?>
            <tr>
                <td><a href="<?= View::url('colisage/expeditions/' . $e['id']) ?>" class="link-tracking"><strong><?= View::e($e['reference']) ?></strong></a></td>
                <td>
                    <span class="material-icons" title="<?= $e['transport_type'] ?>" style="font-size:1.2rem;">
                        <?= $transportIcons[$e['transport_type']] ?? 'local_shipping' ?>
                    </span>
                    <small><?= $e['transport_type'] ?></small>
                </td>
                <td>
                    <small style="display:block; color:var(--color-muted);">↑ <?= View::e($e['departure_agency'] ?? '—') ?></small>
                    <small style="display:block; color:var(--color-muted);">↓ <?= View::e($e['arrival_agency'] ?? '—') ?></small>
                </td>
                <td><span class="badge badge-info"><?= (int)$e['nb_colis'] ?> colis</span></td>
                <td><?= $e['departure_date'] ? date('d/m/Y', strtotime($e['departure_date'])) : '—' ?></td>
                <td><?= $e['estimated_arrival_date'] ? date('d/m/Y', strtotime($e['estimated_arrival_date'])) : '—' ?></td>
                <td><?php $st = $statusLabels[$e['status']] ?? ['label' => $e['status'], 'class' => 'badge-default']; ?><span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span></td>
                <td><a href="<?= View::url('colisage/expeditions/' . $e['id']) ?>" class="btn btn-sm btn-ghost"><span class="material-icons">visibility</span></a></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
