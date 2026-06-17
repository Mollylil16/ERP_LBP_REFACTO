<?php
/** @var array $factures */

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Gestion Financière',
            'Paiements Prestataires',
            'Suivi et décaissement des factures des prestataires (douanes, compagnies aériennes, transporteurs).',
            '',
            ['class' => 'rh-hero']
        ) ?>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>Date facture</th>
                        <th>Prestataire</th>
                        <th>N° Facture</th>
                        <th>Échéance</th>
                        <th>Montant</th>
                        <th>Déjà payé</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($factures)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:20px; color:var(--finea-muted);">Aucune facture prestataire enregistrée.</td>
                        </tr>
                    <?php else: foreach ($factures as $f): 
                        $total = (float)$f['amount'];
                        $paid = (float)$f['amount_paid'];
                        $remains = max(0.0, $total - $paid);
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($f['created_at'])) ?></td>
                            <td><strong><?= View::e($f['prestataire_name'] ?? '—') ?></strong></td>
                            <td><?= View::e($f['invoice_number']) ?></td>
                            <td><?= $f['due_date'] ? date('d/m/Y', strtotime($f['due_date'])) : '—' ?></td>
                            <td><strong><?= number_format($total, 2, ',', ' ') ?> XOF</strong></td>
                            <td style="color:var(--finea-success);"><?= number_format($paid, 2, ',', ' ') ?> XOF</td>
                            <td>
                                <?php if ($f['status'] === 'PAYEE'): ?>
                                    <?= Ui::badge('Payée', 'success') ?>
                                <?php else: ?>
                                    <?= Ui::badge('En attente', 'warning') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($f['status'] !== 'PAYEE'): ?>
                                    <?= Ui::button('Décaisser / Payer', [
                                        'type' => 'button',
                                        'variant' => 'danger',
                                        'size' => 'sm',
                                        'class' => 'btn-pay-prestataire',
                                        'data-id' => $f['id'],
                                        'data-amount' => $remains,
                                        'data-number' => $f['invoice_number'],
                                        'data-prestataire' => $f['prestataire_name']
                                    ]) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<!-- Modal Décaissement Prestataire -->
<dialog id="modal-pay" class="finea-section-card" style="border: none; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-width: 450px; padding: 0; overflow: hidden; background: #fff;">
    <div style="padding: 24px; border-bottom: 1px solid var(--finea-border); display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
        <h3 class="finea-section-title" style="color:var(--finea-danger); margin: 0;">Règlement Prestataire</h3>
        <span class="material-icons" style="cursor: pointer; color: var(--finea-muted);" onclick="document.getElementById('modal-pay').close()">close</span>
    </div>
    <form method="POST" id="form-pay" style="padding: 24px; margin: 0;">
        <?= Csrf::input() ?>
        <p style="margin-bottom: 20px; font-size: 0.95rem; line-height: 1.4;">
            Vous êtes sur le point de valider le décaissement de fonds pour la facture <strong id="lbl-number"></strong> du prestataire <strong id="lbl-prestataire"></strong>.
        </p>

        <div style="margin-bottom: 15px;">
            <?= Form::input('amount', 'Montant à décaisser (XOF)', 0, [
                'type' => 'number',
                'required' => true,
                'min' => 1,
                'step' => '0.01',
                'id' => 'input-amount'
            ]) ?>
        </div>

        <div style="margin-bottom: 20px;">
            <?= Form::input('reference', 'Référence du paiement (N° Chèque / Transac)', '', [
                'placeholder' => 'ex: CH-87421'
            ]) ?>
        </div>

        <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
            <?= Ui::button('Annuler', [
                'type' => 'button', 
                'variant' => 'secondary', 
                'onclick' => "document.getElementById('modal-pay').close()"
            ]) ?>
            <?= Ui::button('Confirmer le paiement', [
                'type' => 'submit', 
                'variant' => 'danger'
            ]) ?>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('.btn-pay-prestataire').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const amount = this.getAttribute('data-amount');
        const number = this.getAttribute('data-number');
        const prestataire = this.getAttribute('data-prestataire');

        document.getElementById('lbl-number').textContent = number;
        document.getElementById('lbl-prestataire').textContent = prestataire;
        document.getElementById('input-amount').value = amount;
        document.getElementById('input-amount').max = amount;

        const form = document.getElementById('form-pay');
        form.action = '<?= View::url('finance/decaissements/') ?>' + id + '/payer';
        document.getElementById('modal-pay').showModal();
    });
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
