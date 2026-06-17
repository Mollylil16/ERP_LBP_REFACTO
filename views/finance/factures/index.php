<?php
/** @var array $invoices */

use App\Helpers\View;
use App\View\Components\Ui;

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Gestion Financière',
            'Règlements Clients',
            'Enregistrement et suivi des règlements de factures clients.',
            '',
            ['class' => 'rh-hero']
        ) ?>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Montant TTC</th>
                        <th>Déjà payé</th>
                        <th>Reste à payer</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:20px; color:var(--finea-muted);">Aucune facture enregistrée pour votre agence.</td>
                        </tr>
                    <?php else: foreach ($invoices as $inv): 
                        $total = (float)$inv['amount_ttc'];
                        $paid = (float)$inv['paid_amount'];
                        $remains = max(0.0, $total - $paid);
                        ?>
                        <tr>
                            <td><strong><?= View::e($inv['reference']) ?></strong></td>
                            <td><?= View::e($inv['client_name'] ?? '—') ?></td>
                            <td><?= View::e(strtoupper($inv['type'])) ?></td>
                            <td><strong><?= number_format($total, 2, ',', ' ') ?> XOF</strong></td>
                            <td style="color:var(--finea-success);"><?= number_format($paid, 2, ',', ' ') ?> XOF</td>
                            <td style="color:var(--finea-danger); font-weight:bold;"><?= number_format($remains, 2, ',', ' ') ?> XOF</td>
                            <td>
                                <?php if ($inv['status'] === 'PAYEE'): ?>
                                    <?= Ui::badge('Payée', 'success') ?>
                                <?php elseif ($inv['status'] === 'PARTIELLEMENT_PAYEE'): ?>
                                    <?= Ui::badge('Partiel', 'warning') ?>
                                <?php else: ?>
                                    <?= Ui::badge('Non payée', 'danger') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= View::url('finance/factures/' . $inv['id']) ?>" class="finea-action-btn finea-action-btn--primary finea-action-btn--sm">
                                    Voir / Encaisser
                                </a>
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
