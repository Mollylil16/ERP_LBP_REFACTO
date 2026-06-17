<?php
/** @var array $invoice */
/** @var array $payments */

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Ui;
use App\View\Components\Form;

$total = (float)$invoice['amount_ttc'];
$paid = (float)$invoice['paid_amount'];
$remains = max(0.0, $total - $paid);

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Règlements Clients',
            'Facture #' . $invoice['reference'],
            'Enregistrement de versement et historique des paiements de la facture.',
            Ui::button('Retour à la liste', ['href' => 'finance/factures', 'variant' => 'secondary']) . ' ' .
            Ui::button('Imprimer / Facture LBP', ['href' => 'finance/factures/' . $invoice['id'] . '/imprimer', 'variant' => 'primary', 'target' => '_blank']),
            ['class' => 'rh-hero']
        ) ?>

        <div class="rh-dashboard-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-top:24px;">
            <div style="display:flex; flex-direction:column; gap:24px;">
                <!-- Infos Facture -->
                <section class="finea-section-card">
                    <h2 class="finea-section-title" style="margin-bottom:15px;">Détails de la facture</h2>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <span style="color:var(--finea-muted); font-size:0.85rem; display:block;">Client</span>
                            <strong><?= View::e($invoice['client_name'] ?? '—') ?></strong>
                        </div>
                        <hr style="border:0; border-top:1px solid var(--finea-border); margin:0;">
                        <div>
                            <span style="color:var(--finea-muted); font-size:0.85rem; display:block;">Type</span>
                            <strong><?= View::e(strtoupper($invoice['type'])) ?></strong>
                        </div>
                        <hr style="border:0; border-top:1px solid var(--finea-border); margin:0;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <span style="color:var(--finea-muted); font-size:0.85rem; display:block;">Montant Total TTC</span>
                                <strong style="font-size:1.1rem; color:var(--finea-text);"><?= number_format($total, 2, ',', ' ') ?> XOF</strong>
                            </div>
                            <div>
                                <span style="color:var(--finea-muted); font-size:0.85rem; display:block;">Déjà payé</span>
                                <strong style="font-size:1.1rem; color:var(--finea-success);"><?= number_format($paid, 2, ',', ' ') ?> XOF</strong>
                            </div>
                        </div>
                        <hr style="border:0; border-top:1px solid var(--finea-border); margin:0;">
                        <div>
                            <span style="color:var(--finea-muted); font-size:0.85rem; display:block;">Reste à recouvrer</span>
                            <strong style="font-size:1.4rem; color:var(--finea-danger);"><?= number_format($remains, 2, ',', ' ') ?> XOF</strong>
                        </div>
                    </div>
                </section>

                <!-- Historique paiements -->
                <section class="finea-section-card">
                    <h2 class="finea-section-title" style="margin-bottom:15px;">Historique des encaissements</h2>
                    <div class="finea-table-wrap">
                        <table class="finea-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Mode</th>
                                    <th>Référence</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center; padding:15px; color:var(--finea-muted);">Aucun versement enregistré.</td>
                                    </tr>
                                <?php else: foreach ($payments as $p): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                                        <td><?= View::e($p['payment_method']) ?></td>
                                        <td><?= View::e($p['reference'] ?? '—') ?></td>
                                        <td><strong><?= number_format((float)$p['amount'], 2, ',', ' ') ?> XOF</strong></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Formulaire d'encaissement -->
            <div>
                <?php if ($remains <= 0): ?>
                    <div class="finea-section-card" style="text-align:center; padding:40px;">
                        <span class="material-icons" style="font-size:48px; color:var(--finea-success); margin-bottom:15px;">check_circle</span>
                        <h2 class="finea-section-title" style="color:var(--finea-success);">Facture Entièrement Payée</h2>
                        <p style="color:var(--finea-muted); margin-top:10px;">Cette facture a été entièrement soldée. Aucun paiement supplémentaire n'est requis.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?= View::url('finance/factures/' . $invoice['id'] . '/payer') ?>" class="finea-section-card">
                        <?= Csrf::input() ?>
                        <h2 class="finea-section-title" style="margin-bottom:20px;">Enregistrer un versement</h2>

                        <div class="rh-form-grid" style="grid-template-columns:1fr; gap:15px;">
                            <?= Form::input('amount', 'Montant à encaisser (XOF)', $remains, [
                                'type' => 'number',
                                'required' => true,
                                'min' => 1,
                                'max' => $remains,
                                'step' => '0.01'
                            ]) ?>

                            <?= Form::select('payment_method', 'Mode de paiement', [
                                ['value' => 'ESPECES', 'label' => 'Espèces'],
                                ['value' => 'CHEQUE', 'label' => 'Chèque'],
                                ['value' => 'VIREMENT', 'label' => 'Virement bancaire'],
                                ['value' => 'MOBILE_MONEY', 'label' => 'Mobile Money']
                            ], 'ESPECES') ?>

                            <?= Form::input('reference', 'Référence de transaction / Numéro chèque', '', [
                                'placeholder' => 'ex: CH-45892, VR-90124...'
                            ]) ?>
                        </div>

                        <div class="rh-form-actions" style="margin-top:25px; display:flex; justify-content:flex-end;">
                            <?= Ui::button('Valider l\'encaissement', ['type' => 'submit', 'variant' => 'accent']) ?>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
