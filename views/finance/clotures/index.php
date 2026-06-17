<?php
/** @var array $caisse */
/** @var array $clotures */

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Finance & Trésorerie',
            'Points de Caisse (Clôtures)',
            'Contrôle quotidien de la caisse d’agence, soumission des points de caisse et validations.',
            '',
            ['class' => 'rh-hero']
        ) ?>

        <div class="rh-dashboard-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:24px; margin-top:24px;">
            <!-- Historique des Clôtures -->
            <section class="finea-section-card">
                <h3 class="finea-section-title" style="margin-bottom:15px;">Historique des Clôtures journalières</h3>
                <div class="finea-table-wrap">
                    <table class="finea-table">
                        <thead>
                            <tr>
                                <th>Date pointage</th>
                                <th>Solde Théorique</th>
                                <th>Solde Déclaré</th>
                                <th>Écart</th>
                                <th>Statut</th>
                                <th>Auteur / Validateur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clotures)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:20px; color:var(--finea-muted);">Aucune clôture de caisse soumise.</td>
                                </tr>
                            <?php else: foreach ($clotures as $c): 
                                $diff = (float)$c['declared_balance'] - (float)$c['theoretical_balance'];
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                                    <td><?= number_format((float)$c['theoretical_balance'], 2, ',', ' ') ?> XOF</td>
                                    <td><strong><?= number_format((float)$c['declared_balance'], 2, ',', ' ') ?> XOF</strong></td>
                                    <td style="font-weight:bold; color:<?= $diff == 0 ? 'var(--finea-success)' : 'var(--finea-danger)' ?>;">
                                        <?= ($diff > 0 ? '+' : '') . number_format($diff, 2, ',', ' ') ?> XOF
                                    </td>
                                    <td>
                                        <?php if ($c['status'] === 'VALIDE'): ?>
                                            <?= Ui::badge('Validée', 'success') ?>
                                        <?php elseif ($c['status'] === 'REJETE'): ?>
                                            <?= Ui::badge('Rejetée', 'danger') ?>
                                        <?php else: ?>
                                            <?= Ui::badge('En attente', 'warning') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small style="display:block;">Soumis par : <?= View::e($c['creator_name']) ?></small>
                                        <?php if ($c['status'] === 'VALIDE'): ?>
                                            <small style="color:var(--finea-success);">Approuvé par : <?= View::e($c['validator_name'] ?? '—') ?></small>
                                        <?php elseif ($c['status'] === 'REJETE'): ?>
                                            <small style="color:var(--finea-danger);">Rejeté par : <?= View::e($c['validator_name'] ?? '—') ?></small>
                                            <?php if ($c['rejection_reason']): ?>
                                                <div style="font-size:0.75rem; background:#fee2e2; color:#991b1b; padding:5px; border-radius:4px; margin-top:3px;">
                                                    Motif : <?= View::e($c['rejection_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
 
                                        <?php if ($c['status'] === 'EN_ATTENTE' && \App\Helpers\Auth::user()?->isAdmin): ?>
                                            <div style="display:flex; gap:5px; margin-top:5px;">
                                                <form method="POST" action="<?= View::url('finance/clotures/' . $c['id'] . '/valider') ?>" style="margin:0;">
                                                    <?= Csrf::input() ?>
                                                    <input type="hidden" name="status" value="VALIDE">
                                                    <?= Ui::button('Valider', ['type' => 'submit', 'variant' => 'success', 'size' => 'sm']) ?>
                                                </form>
                                                <?= Ui::button('Rejeter', [
                                                    'type' => 'button',
                                                    'variant' => 'danger',
                                                    'size' => 'sm',
                                                    'class' => 'btn-rejeter-cloture',
                                                    'data-id' => $c['id']
                                                ]) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
 
            <!-- Soumission de Point de Caisse -->
            <div>
                <?php if ($caisse['status'] === 'FERMEE'): ?>
                    <div class="finea-section-card" style="text-align:center; padding:30px;">
                        <span class="material-icons" style="font-size:48px; color:var(--finea-muted); margin-bottom:12px;">lock</span>
                        <h3 class="finea-section-title">Caisse Actuellement Fermée</h3>
                        <p style="font-size:0.85rem; color:var(--finea-muted); margin-top:10px;">
                            Pour ouvrir la caisse et recevoir des règlements ou effectuer des décaissements, veuillez y ajouter un mouvement d'approvisionnement.
                        </p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?= View::url('finance/clotures') ?>" class="finea-section-card">
                        <?= Csrf::input() ?>
                        <h3 class="finea-section-title" style="margin-bottom:15px;">Faire la clôture de caisse</h3>
                        <p style="font-size:0.85rem; color:var(--finea-muted); margin-bottom:20px;">
                            Saisissez le montant en espèces physique constaté dans le tiroir-caisse pour soumettre le point de fin de journée.
                        </p>
 
                        <div style="margin-bottom:15px; background:#f8fafc; padding:15px; border-radius:8px; border:1px dashed var(--finea-border);">
                            <span style="font-size:0.8rem; color:var(--finea-muted); display:block;">Solde théorique attendu</span>
                            <strong style="font-size:1.3rem; color:var(--finea-primary);"><?= number_format((float)$caisse['balance'], 2, ',', ' ') ?> XOF</strong>
                        </div>
 
                        <div style="margin-bottom:20px;">
                            <?= Form::input('declared_balance', 'Montant physique constaté (XOF)', '', [
                                'type' => 'number',
                                'required' => true,
                                'min' => 0,
                                'step' => '0.01'
                            ]) ?>
                        </div>

                        <?= Ui::button('Soumettre le Point de Caisse', [
                            'type' => 'submit',
                            'variant' => 'accent',
                            'style' => 'width:100%;',
                            'onclick' => "return confirm('Confirmer la soumission de cette clôture ? La caisse sera temporairement bloquée.')"
                        ]) ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Rejet Clôture -->
<dialog id="modal-rejet-cloture" class="finea-section-card" style="border: none; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-width: 450px; padding: 0; overflow: hidden; background: #fff;">
    <div style="padding: 24px; border-bottom: 1px solid var(--finea-border); display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
        <h3 class="finea-section-title" style="color:var(--finea-danger); margin: 0;">Motif du rejet</h3>
        <span class="material-icons" style="cursor: pointer; color: var(--finea-muted);" onclick="document.getElementById('modal-rejet-cloture').close()">close</span>
    </div>
    <form method="POST" id="form-rejet-cloture" style="padding: 24px; margin: 0;">
        <?= Csrf::input() ?>
        <input type="hidden" name="status" value="REJETE">
        <div style="margin-bottom: 20px;">
            <?= Form::textarea('rejection_reason', 'Raison du rejet (obligatoire)', '', [
                'required' => true,
                'rows' => 3,
                'placeholder' => 'Écart trop important non justifié, etc.'
            ]) ?>
        </div>
        <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
            <?= Ui::button('Annuler', [
                'type' => 'button', 
                'variant' => 'secondary', 
                'onclick' => "document.getElementById('modal-rejet-cloture').close()"
            ]) ?>
            <?= Ui::button('Rejeter la clôture', [
                'type' => 'submit', 
                'variant' => 'danger'
            ]) ?>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('.btn-rejeter-cloture').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const form = document.getElementById('form-rejet-cloture');
        form.action = '<?= View::url('finance/clotures/') ?>' + id + '/valider';
        document.getElementById('modal-rejet-cloture').showModal();
    });
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
