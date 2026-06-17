<?php
/** @var array $inventaires */
use App\Helpers\View;
use App\View\Components\Ui;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Colisage', 'Inventaires d\'entrepôt', [
            'actions' => Ui::button('Nouvel inventaire', 'colisage/inventaire/nouveau', [
                'variant' => 'primary'
            ])
        ]) ?>

        <div class="finea-section-card" style="margin-top: 1.5rem;">
            <div class="finea-table-wrap">
                <table class="finea-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Agence</th>
                            <th>Statut</th>
                            <th>Nb scanné</th>
                            <th>Manquants</th>
                            <th>Démarré le</th>
                            <th>Clôturé le</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventaires)): ?>
                        <tr>
                            <td colspan="8" class="empty-row" style="text-align: center; padding: 2rem;">
                                <?= Ui::emptyState('Aucune campagne', 'Démarrez un inventaire physique en entrepôt pour recenser les colis.', 'inventory') ?>
                            </td>
                        </tr>
                        <?php else: foreach ($inventaires as $inv): ?>
                        <tr>
                            <td><strong>#<?= $inv['id'] ?></strong></td>
                            <td><?= View::e($inv['agency_name'] ?? '—') ?></td>
                            <td>
                                <?php if ($inv['status'] === 'EN_COURS'): ?>
                                <?= Ui::badge('En cours', 'warning') ?>
                                <?php else: ?>
                                <?= Ui::badge('Clôturé', 'success') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= Ui::badge((string)$inv['nb_scanned'], 'info') ?></td>
                            <td>
                                <?php if ((int)$inv['nb_missing'] > 0): ?>
                                <?= Ui::badge($inv['nb_missing'] . ' manquant(s)', 'danger') ?>
                                <?php else: ?>
                                <span style="color:var(--finea-success); font-weight: 700;">✓ Aucun</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($inv['started_at'])) ?></td>
                            <td><?= $inv['closed_at'] ? date('d/m/Y H:i', strtotime($inv['closed_at'])) : '—' ?></td>
                            <td>
                                <a href="<?= View::url('colisage/inventaire/' . $inv['id']) ?>" class="finea-action-btn finea-action-btn--ghost" style="padding: 4px 8px; min-height: auto;">
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
