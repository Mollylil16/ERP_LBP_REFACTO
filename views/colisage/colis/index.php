<?php
/** @var array $colisList */
/** @var array $agencies */
/** @var array $filters */
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

$statusLabels = [
    'RECEPTIONNE'    => ['label' => 'Réceptionné',     'tone' => 'warning'],
    'EN_PREPARATION' => ['label' => 'En préparation',  'tone' => 'info'],
    'EN_TRANSIT'     => ['label' => 'En transit',      'tone' => 'purple'],
    'ARRIVE'         => ['label' => 'Arrivé',          'tone' => 'success'],
    'RETIRE'         => ['label' => 'Retiré',          'tone' => 'neutral'],
];

$statusOptions = [['value' => '', 'label' => 'Tous les statuts']];
foreach ($statusLabels as $val => $info) {
    $statusOptions[] = ['value' => $val, 'label' => $info['label']];
}

$agencyOptions = [['value' => '', 'label' => 'Toutes les agences']];
foreach ($agencies as $a) {
    $agencyOptions[] = ['value' => $a['id'], 'label' => $a['name']];
}
?>

<?= Ui::pageHeader('Colisage', 'Gestion des Colis', [
    'actions' => Ui::button('Nouveau Colis', 'colisage/colis/nouveau', [
        'variant' => 'primary',
        'html' => true,
    ])
]) ?>

<!-- Filtres -->
<form method="GET" action="<?= View::url('colisage/colis') ?>" class="finea-section-card" style="padding:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
        <div style="flex:1; min-width:200px;">
            <?= Form::input('search', 'Recherche (tracking, client)', $filters['search'] ?? '', ['placeholder' => 'N° tracking ou nom client...', 'fieldClass' => 'margin-0']) ?>
        </div>
        <div style="min-width:160px;">
            <?= Form::select('status', 'Statut', $statusOptions, $filters['status'] ?? '', ['fieldClass' => 'margin-0']) ?>
        </div>
        <div style="min-width:160px;">
            <?= Form::select('agency_id', 'Agence', $agencyOptions, $filters['agency_id'] ?? '', ['fieldClass' => 'margin-0']) ?>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <?= Ui::button('Filtrer', null, ['type' => 'submit', 'variant' => 'outline']) ?>
            <?= Ui::button('Réinitialiser', 'colisage/colis', ['variant' => 'ghost']) ?>
        </div>
    </div>
</form>

<!-- Table colis -->
<div class="finea-section-card">
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
                <td colspan="9" class="empty-row" style="text-align: center; padding: 2rem;">
                    <?= Ui::emptyState('Aucun colis trouvé', 'Essayez d\'ajuster vos filtres ou créez un nouveau colis.', 'inbox') ?>
                </td>
            </tr>
            <?php else: foreach ($colisList as $c): ?>
            <tr>
                <td>
                    <a href="<?= View::url('colisage/colis/' . $c['id']) ?>" class="link-tracking" style="color: var(--color-accent); font-weight: 600; text-decoration: none;">
                        <?= View::e($c['tracking_number']) ?>
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
                    <?php $st = $statusLabels[$c['status']] ?? ['label' => $c['status'], 'tone' => 'neutral']; ?>
                    <?= Ui::badge($st['label'], $st['tone']) ?>
                </td>
                <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <div style="display: flex; gap: 0.25rem;">
                        <a href="<?= View::url('colisage/colis/' . $c['id']) ?>" class="finea-action-btn finea-action-btn--ghost" title="Voir le détail" style="padding: 4px 8px;">
                            <span class="material-icons" style="font-size: 1.2rem;">visibility</span>
                        </a>
                        <?php if ($c['status'] === 'ARRIVE'): ?>
                        <a href="<?= View::url('colisage/colis/' . $c['id'] . '/retrait') ?>" class="finea-action-btn finea-action-btn--success" title="Remettre au destinataire" style="padding: 4px 8px;">
                            <span class="material-icons" style="font-size: 1.2rem;">how_to_reg</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
