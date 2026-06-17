<?php
/** @var array $credits */
/** @var array $filters */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne',
            'Crédits Inter-Agences',
            'Gestion des dettes et transactions financières entre agences.',
            Ui::button('Nouveau transfert / Dette', ['href' => 'logistique/credits/nouveau', 'variant' => 'accent']),
            ['class' => 'rh-hero']
        ) ?>

        <form method="GET" action="<?= View::url('logistique/credits') ?>" class="finea-section-card" style="margin-top: 24px;">
            <div class="rh-form-grid" style="grid-template-columns: 2fr auto; gap: 15px; align-items: flex-end;">
                <?= Form::select('status', 'Statut', [
                    ['value' => '', 'label' => 'Tous les statuts'],
                    ['value' => 'EN_ATTENTE', 'label' => 'En attente (Non apuré)'],
                    ['value' => 'VALIDE', 'label' => 'Validé (Apuré)']
                ], $filters['status'] ?? '') ?>

                <?= Ui::button('Filtrer', ['type' => 'submit', 'variant' => 'secondary']) ?>
            </div>
        </form>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Agence Débitrice (De)</th>
                        <th>Agence Créancière (Vers)</th>
                        <th>Montant</th>
                        <th>Motif / Réf. Colis</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($credits)): ?>
                        <tr>
                            <td colspan="7">
                                <?= Ui::emptyState('Aucun crédit inter-agences', 'Toutes les dettes et transferts inter-agences sont équilibrés.') ?>
                            </td>
                        </tr>
                    <?php else: foreach ($credits as $c): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                            <td><strong style="color:var(--finea-danger);"><?= View::e($c['from_agency_name']) ?></strong></td>
                            <td><strong style="color:var(--finea-success);"><?= View::e($c['to_agency_name']) ?></strong></td>
                            <td><strong><?= number_format((float)$c['amount'], 0, ',', ' ') ?> <?= View::e($c['currency']) ?></strong></td>
                            <td>
                                <?= View::e($c['reason'] ?? '—') ?><br>
                                <?php if ($c['reference_colis']): ?>
                                    <small style="color:var(--finea-muted);">Réf: <?= View::e($c['reference_colis']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['status'] === 'EN_ATTENTE'): ?>
                                    <?= Ui::badge('Non apuré', 'warning') ?>
                                <?php else: ?>
                                    <?= Ui::badge('Apuré', 'success') ?><br>
                                    <small style="color:var(--finea-muted);"><?= date('d/m/y', strtotime($c['settled_at'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['status'] === 'EN_ATTENTE'): ?>
                                    <form method="POST" action="<?= View::url('logistique/credits/' . $c['id'] . '/apurer') ?>" style="margin:0;">
                                        <?= Csrf::input() ?>
                                        <?= Ui::button('Marquer Apuré', [
                                            'type' => 'submit',
                                            'variant' => 'primary',
                                            'size' => 'sm',
                                            'onclick' => "return confirm('Confirmer l\'apurement de cette dette inter-agences ?')"
                                        ]) ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
