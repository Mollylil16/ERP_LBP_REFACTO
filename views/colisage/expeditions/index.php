<?php
/** @var array $expeditions */
/** @var array $filters */
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

$statusLabels = [
    'PLANIFIE' => ['label' => 'Planifié',   'tone' => 'info'],
    'EN_COURS' => ['label' => 'En cours',   'tone' => 'purple'],
    'ARRIVE'   => ['label' => 'Arrivé',     'tone' => 'success'],
    'CLOTURE'  => ['label' => 'Clôturé',    'tone' => 'neutral'],
];
$transportIcons = ['AERIEN' => 'flight', 'MARITIME' => 'directions_boat', 'TERRESTRE' => 'local_shipping'];

$statusOptions = [['value' => '', 'label' => 'Tous']];
foreach ($statusLabels as $val => $info) {
    $statusOptions[] = ['value' => $val, 'label' => $info['label']];
}
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Colisage', 'Expéditions & Manifestes', [
            'actions' => Ui::button('Nouvelle expédition', 'colisage/expeditions/nouveau', [
                'variant' => 'primary'
            ])
        ]) ?>

        <!-- Filtres -->
        <form method="GET" action="<?= View::url('colisage/expeditions') ?>" class="finea-section-card" style="padding:1rem; margin-top: 1.5rem; margin-bottom:1.5rem;">
            <div style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
                <div style="min-width:150px;">
                    <?= Form::select('status', 'Statut', $statusOptions, $filters['status'] ?? '', ['fieldClass' => 'margin-0']) ?>
                </div>
                <div style="min-width:160px;">
                    <?= Form::select('transport_type', 'Mode de transport', [
                        ['value' => '', 'label' => 'Tous'],
                        ['value' => 'AERIEN', 'label' => 'Aérien'],
                        ['value' => 'MARITIME', 'label' => 'Maritime'],
                        ['value' => 'TERRESTRE', 'label' => 'Terrestre'],
                    ], $filters['transport_type'] ?? '', ['fieldClass' => 'margin-0']) ?>
                </div>
                <?= Ui::button('Filtrer', null, ['type' => 'submit', 'variant' => 'outline']) ?>
            </div>
        </form>

        <!-- Table expéditions -->
        <div class="finea-section-card">
            <div class="finea-table-wrap">
                <table class="finea-table">
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
                        <tr>
                            <td colspan="8" class="empty-row" style="text-align: center; padding: 2rem;">
                                <?= Ui::emptyState('Aucune expédition', 'Essayez d\'ajouter une nouvelle expédition de groupage.', 'local_shipping') ?>
                            </td>
                        </tr>
                        <?php else: foreach ($expeditions as $e): ?>
                        <tr>
                            <td>
                                <a href="<?= View::url('colisage/expeditions/' . $e['id']) ?>" style="color: var(--color-accent); font-weight: 600; text-decoration: none;">
                                    <strong><?= View::e($e['reference']) ?></strong>
                                </a>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="material-icons" title="<?= $e['transport_type'] ?>" style="font-size:1.2rem; color: var(--finea-navy);">
                                        <?= $transportIcons[$e['transport_type']] ?? 'local_shipping' ?>
                                    </span>
                                    <small><?= $e['transport_type'] ?></small>
                                </div>
                            </td>
                            <td>
                                <small style="display:block; color:var(--color-muted);">↑ <?= View::e($e['departure_agency'] ?? '—') ?></small>
                                <small style="display:block; color:var(--color-muted);">↓ <?= View::e($e['arrival_agency'] ?? '—') ?></small>
                            </td>
                            <td><?= Ui::badge($e['nb_colis'] . ' colis', 'info') ?></td>
                            <td><?= $e['departure_date'] ? date('d/m/Y', strtotime($e['departure_date'])) : '—' ?></td>
                            <td><?= $e['estimated_arrival_date'] ? date('d/m/Y', strtotime($e['estimated_arrival_date'])) : '—' ?></td>
                            <td>
                                <?php $st = $statusLabels[$e['status']] ?? ['label' => $e['status'], 'tone' => 'neutral']; ?>
                                <?= Ui::badge($st['label'], $st['tone']) ?>
                            </td>
                            <td>
                                <a href="<?= View::url('colisage/expeditions/' . $e['id']) ?>" class="finea-action-btn finea-action-btn--ghost" style="padding: 4px 8px; min-height: auto;">
                                    <span class="material-icons" style="font-size: 1.2rem;">visibility</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
