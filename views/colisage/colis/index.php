<?php
/** @var array $colisList */
/** @var array $agencies */
/** @var array $filters */
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';

$statusLabels = [
    'RECEPTIONNE'    => ['label' => 'Réceptionné',     'class' => 'badge-warning'],
    'EN_PREPARATION' => ['label' => 'En préparation',  'class' => 'badge-info'],
    'EN_TRANSIT'     => ['label' => 'En transit',      'class' => 'badge-purple'],
    'ARRIVE'         => ['label' => 'Arrivé',          'class' => 'badge-success'],
    'RETIRE'         => ['label' => 'Retiré',          'class' => 'badge-default'],
];
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage</p>
        <h1>Gestion des Colis</h1>
    </div>
    <div class="header-actions">
        <a href="<?= View::url('colisage/colis/nouveau') ?>" class="btn btn-primary">
            <span class="material-icons">add_box</span> Nouveau Colis
        </a>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="<?= View::url('colisage/colis') ?>" class="filter-bar card" style="padding:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
        <div class="form-group" style="margin:0; flex:1; min-width:200px;">
            <label>Recherche (tracking, client)</label>
            <input type="text" name="search" value="<?= View::e($filters['search'] ?? '') ?>" class="form-input" placeholder="N° tracking ou nom client...">
        </div>
        <div class="form-group" style="margin:0; min-width:160px;">
            <label>Statut</label>
            <select name="status" class="form-select">
                <option value="">Tous les statuts</option>
                <?php foreach ($statusLabels as $val => $info): ?>
                <option value="<?= $val ?>" <?= ($filters['status'] ?? '') === $val ? 'selected' : '' ?>><?= $info['label'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0; min-width:160px;">
            <label>Agence</label>
            <select name="agency_id" class="form-select">
                <option value="">Toutes les agences</option>
                <?php foreach ($agencies as $a): ?>
                <option value="<?= $a['id'] ?>" <?= ($filters['agency_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= View::e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">
            <span class="material-icons">search</span> Filtrer
        </button>
        <a href="<?= View::url('colisage/colis') ?>" class="btn btn-ghost">Réinitialiser</a>
    </div>
</form>

<!-- Table colis -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>N° Tracking</th>
                <th>Expéditeur</th>
                <th>Destinataire</th>
                <th>Trajet</th>
                <th>Poids</th>
                <th>Prix</th>
                <th>Statut</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($colisList)): ?>
            <tr>
                <td colspan="9" class="empty-row">
                    <span class="material-icons">inbox</span>
                    Aucun colis trouvé.
                </td>
            </tr>
            <?php else: foreach ($colisList as $c): ?>
            <tr>
                <td>
                    <a href="<?= View::url('colisage/colis/' . $c['id']) ?>" class="link-tracking">
                        <strong><?= View::e($c['tracking_number']) ?></strong>
                    </a>
                </td>
                <td><?= View::e($c['sender_name'] ?? '—') ?></td>
                <td><?= View::e($c['receiver_name'] ?? '—') ?></td>
                <td>
                    <small style="display:block; color:var(--color-muted);">↑ <?= View::e($c['departure_agency'] ?? '—') ?></small>
                    <small style="display:block; color:var(--color-muted);">↓ <?= View::e($c['arrival_agency'] ?? '—') ?></small>
                </td>
                <td><?= number_format((float)$c['total_weight'], 2) ?> kg</td>
                <td><?= number_format((float)$c['total_price'], 0, ',', ' ') ?> <?= View::e($c['currency']) ?></td>
                <td>
                    <?php $st = $statusLabels[$c['status']] ?? ['label' => $c['status'], 'class' => 'badge-default']; ?>
                    <span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
                </td>
                <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <a href="<?= View::url('colisage/colis/' . $c['id']) ?>" class="btn btn-sm btn-ghost" title="Voir le détail">
                        <span class="material-icons">visibility</span>
                    </a>
                    <?php if ($c['status'] === 'ARRIVE'): ?>
                    <a href="<?= View::url('colisage/colis/' . $c['id'] . '/retrait') ?>" class="btn btn-sm btn-success" title="Remettre au destinataire">
                        <span class="material-icons">how_to_reg</span>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
