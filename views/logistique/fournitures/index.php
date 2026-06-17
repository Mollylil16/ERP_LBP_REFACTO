<?php
/** @var array $fournitures */
/** @var array $filters */
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

// Prepare agencies options
$agenciesOptions = [['value' => '', 'label' => 'Toutes']];
foreach ($agencies as $a) {
    $agenciesOptions[] = ['value' => (string)$a['id'], 'label' => $a['name']];
}
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne',
            'Fournitures & Consommables',
            'Gestion des demandes de fournitures pour les différentes agences.',
            Ui::button('Nouvelle demande', ['href' => 'logistique/fournitures/nouvelle', 'variant' => 'accent']),
            ['class' => 'rh-hero']
        ) ?>

        <form method="GET" action="<?= View::url('logistique/fournitures') ?>" class="finea-section-card" style="margin-top: 24px;">
            <div class="rh-form-grid" style="grid-template-columns: 2fr 1.5fr auto; gap: 15px; align-items: flex-end;">
                <?= Form::select('agency_id', 'Agence', $agenciesOptions, $filters['agency_id'] ?? '') ?>

                <?= Form::select('status', 'Statut', [
                    ['value' => '', 'label' => 'Tous les statuts'],
                    ['value' => 'EN_ATTENTE', 'label' => 'En attente'],
                    ['value' => 'APPROUVEE', 'label' => 'Approuvée (à livrer)'],
                    ['value' => 'LIVREE', 'label' => 'Livrée'],
                    ['value' => 'REJETEE', 'label' => 'Rejetée']
                ], $filters['status'] ?? '') ?>

                <?= Ui::button('Filtrer', ['type' => 'submit', 'variant' => 'secondary']) ?>
            </div>
        </form>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>Date demande</th>
                        <th>Agence</th>
                        <th>Demandeur</th>
                        <th>Articles demandés</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fournitures)): ?>
                        <tr>
                            <td colspan="6">
                                <?= Ui::emptyState('Aucune demande de fourniture', 'Commencez par initier une nouvelle demande.') ?>
                            </td>
                        </tr>
                    <?php else: foreach ($fournitures as $f): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($f['created_at'])) ?></td>
                            <td><strong><?= View::e($f['agency_name']) ?></strong></td>
                            <td><?= View::e($f['requested_by_name'] ?? '—') ?></td>
                            <td><pre style="margin:0; font-family:inherit; font-size:.85rem; max-width:300px; white-space:pre-wrap;"><?= View::e($f['items_requested']) ?></pre></td>
                            <td>
                                <?php if ($f['status'] === 'EN_ATTENTE'): ?>
                                    <?= Ui::badge('En attente', 'warning') ?>
                                <?php elseif ($f['status'] === 'APPROUVEE'): ?>
                                    <?= Ui::badge('Approuvée', 'info') ?>
                                <?php elseif ($f['status'] === 'LIVREE'): ?>
                                    <?= Ui::badge('Livrée', 'success') ?><br>
                                    <small style="color:var(--finea-muted);">le <?= date('d/m/y', strtotime($f['delivered_at'])) ?></small>
                                <?php elseif ($f['status'] === 'REJETEE'): ?>
                                    <?= Ui::badge('Rejetée', 'danger') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($f['status'] === 'EN_ATTENTE'): ?>
                                    <div style="display:flex; gap:.5rem;">
                                        <form method="POST" action="<?= View::url('logistique/fournitures/' . $f['id'] . '/valider') ?>" style="margin:0;">
                                            <?= Csrf::input() ?>
                                            <?= Ui::button('<span class="material-icons" style="font-size:16px;">check</span>', [
                                                'type' => 'submit', 
                                                'variant' => 'success', 
                                                'size' => 'sm',
                                                'title' => 'Approuver'
                                            ]) ?>
                                        </form>
                                        <?= Ui::button('<span class="material-icons" style="font-size:16px;">close</span>', [
                                            'type' => 'button', 
                                            'variant' => 'danger', 
                                            'size' => 'sm', 
                                            'class' => 'btn-rejeter', 
                                            'data-id' => $f['id'], 
                                            'title' => 'Rejeter'
                                        ]) ?>
                                    </div>
                                <?php elseif ($f['status'] === 'APPROUVEE'): ?>
                                    <form method="POST" action="<?= View::url('logistique/fournitures/' . $f['id'] . '/livrer') ?>" style="margin:0;">
                                        <?= Csrf::input() ?>
                                        <?= Ui::button('Marquer Livrée', [
                                            'type' => 'submit',
                                            'variant' => 'primary',
                                            'size' => 'sm',
                                            'onclick' => "return confirm('Confirmer la livraison de ces fournitures à l\'agence ?')"
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

<!-- Modal Rejet -->
<dialog id="modal-rejet" class="finea-section-card" style="border: none; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-width: 450px; padding: 0; overflow: hidden; background: #fff;">
    <div style="padding: 24px; border-bottom: 1px solid var(--finea-border); display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
        <h3 class="finea-section-title" style="color:var(--finea-danger); margin: 0;">Motif du rejet</h3>
        <span class="material-icons" style="cursor: pointer; color: var(--finea-muted);" onclick="document.getElementById('modal-rejet').close()">close</span>
    </div>
    <form method="POST" id="form-rejet" style="padding: 24px; margin: 0;">
        <?= Csrf::input() ?>
        <div style="margin-bottom: 20px;">
            <?= Form::textarea('rejection_reason', 'Raison du rejet (obligatoire)', '', [
                'required' => true,
                'rows' => 3,
                'placeholder' => 'Rupture de stock, demande non justifiée, etc.'
            ]) ?>
        </div>
        <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
            <?= Ui::button('Annuler', [
                'type' => 'button', 
                'variant' => 'secondary', 
                'onclick' => "document.getElementById('modal-rejet').close()"
            ]) ?>
            <?= Ui::button('Confirmer le rejet', [
                'type' => 'submit', 
                'variant' => 'danger'
            ]) ?>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('.btn-rejeter').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const form = document.getElementById('form-rejet');
        form.action = '<?= View::url('logistique/fournitures/') ?>' + id + '/rejeter';
        document.getElementById('modal-rejet').showModal();
    });
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
