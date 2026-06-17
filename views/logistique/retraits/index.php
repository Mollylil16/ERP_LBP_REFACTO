<?php
/** @var array $retraits */
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
            'Retraits Hub (Décaissements)',
            'Validation et suivi des paiements aux prestataires depuis la caisse centrale.',
            '',
            ['class' => 'rh-hero']
        ) ?>

        <form method="GET" action="<?= View::url('logistique/retraits') ?>" class="finea-section-card" style="margin-top: 24px;">
            <div class="rh-form-grid" style="grid-template-columns: 2fr auto; gap: 15px; align-items: flex-end;">
                <?= Form::select('status', 'Statut d\'approbation', [
                    ['value' => '', 'label' => 'Tous les statuts'],
                    ['value' => 'EN_ATTENTE', 'label' => 'En attente (À valider)'],
                    ['value' => 'APPROUVE', 'label' => 'Approuvé'],
                    ['value' => 'REFUSE', 'label' => 'Refusé']
                ], $filters['status'] ?? '') ?>

                <?= Ui::button('Filtrer', ['type' => 'submit', 'variant' => 'secondary']) ?>
            </div>
        </form>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>Date demande</th>
                        <th>Prestataire / Facture</th>
                        <th>Montant</th>
                        <th>Référence / Transaction</th>
                        <th>Initié par</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($retraits)): ?>
                        <tr>
                            <td colspan="7">
                                <?= Ui::emptyState('Aucun retrait trouvé', 'Tous les décaissements sont à jour.') ?>
                            </td>
                        </tr>
                    <?php else: foreach ($retraits as $r): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($r['payment_date'])) ?></td>
                            <td>
                                <strong><?= View::e($r['prestataire_name']) ?></strong><br>
                                <a href="<?= View::url('logistique/factures/' . $r['facture_id']) ?>" class="link-tracking" style="font-size:.85rem; color: var(--module-accent); font-weight: 600;">
                                    Facture: <?= View::e($r['invoice_number']) ?>
                                </a>
                            </td>
                            <td><strong style="color:var(--finea-success); font-size:1.1rem;"><?= number_format((float)$r['amount_paid'], 0, ',', ' ') ?> <?= View::e($r['currency']) ?></strong></td>
                            <td><?= View::e($r['reference_transaction'] ?? '—') ?></td>
                            <td><?= View::e($r['recorded_by_name'] ?? '—') ?></td>
                            <td>
                                <?php if ($r['status'] === 'EN_ATTENTE'): ?>
                                    <?= Ui::badge('En attente', 'warning') ?>
                                <?php elseif ($r['status'] === 'APPROUVE'): ?>
                                    <?= Ui::badge('Approuvé', 'success') ?>
                                <?php elseif ($r['status'] === 'REFUSE'): ?>
                                    <?= Ui::badge('Refusé', 'danger') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'EN_ATTENTE'): ?>
                                    <div style="display:flex; gap:.5rem;">
                                        <form method="POST" action="<?= View::url('logistique/retraits/' . $r['id'] . '/approuver') ?>" style="margin:0;">
                                            <?= Csrf::input() ?>
                                            <?= Ui::button('<span class="material-icons" style="font-size:16px;">check</span>', [
                                                'type' => 'submit', 
                                                'variant' => 'success', 
                                                'size' => 'sm',
                                                'title' => 'Approuver le décaissement',
                                                'onclick' => 'return confirm("Confirmer l\'approbation et le décaissement ?")'
                                            ]) ?>
                                        </form>
                                        <?= Ui::button('<span class="material-icons" style="font-size:16px;">close</span>', [
                                            'type' => 'button', 
                                            'variant' => 'danger', 
                                            'size' => 'sm', 
                                            'class' => 'btn-refuser', 
                                            'data-id' => $r['id'], 
                                            'title' => 'Refuser'
                                        ]) ?>
                                    </div>
                                <?php else: ?>
                                    <small style="color:var(--finea-muted);">Traité par <?= View::e($r['approved_by_name'] ?? '—') ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<!-- Modal Refus -->
<dialog id="modal-refus" class="finea-section-card" style="border: none; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-width: 450px; padding: 0; overflow: hidden; background: #fff;">
    <div style="padding: 24px; border-bottom: 1px solid var(--finea-border); display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
        <h3 class="finea-section-title" style="color:var(--finea-danger); margin: 0;">Motif du refus</h3>
        <span class="material-icons" style="cursor: pointer; color: var(--finea-muted);" onclick="document.getElementById('modal-refus').close()">close</span>
    </div>
    <form method="POST" id="form-refus" style="padding: 24px; margin: 0;">
        <?= Csrf::input() ?>
        <div style="margin-bottom: 20px;">
            <?= Form::textarea('rejection_reason', 'Raison du refus (obligatoire)', '', [
                'required' => true,
                'rows' => 3,
                'placeholder' => 'Erreur montant, RIB incorrect, etc.'
            ]) ?>
        </div>
        <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
            <?= Ui::button('Annuler', [
                'type' => 'button', 
                'variant' => 'secondary', 
                'onclick' => "document.getElementById('modal-refus').close()"
            ]) ?>
            <?= Ui::button('Confirmer le refus', [
                'type' => 'submit', 
                'variant' => 'danger'
            ]) ?>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('.btn-refuser').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const form = document.getElementById('form-refus');
        form.action = '<?= View::url('logistique/retraits/') ?>' + id + '/refuser';
        document.getElementById('modal-refus').showModal();
    });
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
