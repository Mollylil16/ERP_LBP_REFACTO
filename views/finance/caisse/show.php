<?php
/** @var array $caisse */
/** @var array $mouvements */

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
            'Journal de Caisse',
            'Consultation du solde physique de la caisse d’agence et historique détaillé des flux.',
            $caisse['status'] === 'OUVERTE' 
                ? Ui::badge('Caisse Ouverte', 'success') 
                : Ui::badge('Caisse Fermée', 'danger'),
            ['class' => 'rh-hero']
        ) ?>

        <div class="rh-dashboard-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:24px; margin-top:24px;">
            <div style="display:flex; flex-direction:column; gap:24px;">
                <!-- Solde Actuel -->
                <section class="finea-section-card" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <span style="color:var(--finea-muted); font-size:0.9rem;">Solde Actuel de Caisse</span>
                        <h2 style="font-size:2.2rem; color:var(--finea-primary); font-weight:bold; margin-top:5px;">
                            <?= number_format((float)$caisse['balance'], 2, ',', ' ') ?> XOF
                        </h2>
                    </div>
                    <div>
                        <span style="color:var(--finea-muted); font-size:0.9rem; display:block; text-align:right;">Dernière mise à jour</span>
                        <strong style="display:block; text-align:right; margin-top:5px;">
                            <?= $caisse['updated_at'] ? date('d/m/Y H:i', strtotime($caisse['updated_at'])) : date('d/m/Y H:i') ?>
                        </strong>
                    </div>
                </section>

                <!-- Liste des Mouvements -->
                <section class="finea-section-card">
                    <h3 class="finea-section-title" style="margin-bottom:15px;">Historique des Mouvements</h3>
                    <div class="finea-table-wrap">
                        <table class="finea-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th>Justification</th>
                                    <th>Auteur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mouvements)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:20px; color:var(--finea-muted);">Aucun mouvement de caisse enregistré.</td>
                                    </tr>
                                <?php else: foreach ($mouvements as $m): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                                        <td>
                                            <?php if ($m['type'] === 'ENTREE'): ?>
                                                <?= Ui::badge('Entrée', 'success') ?>
                                            <?php elseif ($m['type'] === 'APPRO'): ?>
                                                <?= Ui::badge('Approvisionnement', 'info') ?>
                                            <?php else: ?>
                                                <?= Ui::badge('Décaissement', 'danger') ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= number_format((float)$m['amount'], 2, ',', ' ') ?> XOF</strong></td>
                                        <td><?= View::e($m['justification']) ?></td>
                                        <td><?= View::e($m['recorder_name'] ?? 'Système') ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Ajustements caisses -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                <!-- Approvisionnement -->
                <form method="POST" action="<?= View::url('finance/caisse/appro') ?>" class="finea-section-card">
                    <?= Csrf::input() ?>
                    <h3 class="finea-section-title" style="color:var(--finea-primary); margin-bottom:15px;">Approvisionnement</h3>
                    <p style="font-size:0.85rem; color:var(--finea-muted); margin-bottom:15px;">
                        Ajouter des fonds physiques dans la caisse d'agence (ex: retour de banque).
                    </p>

                    <div style="margin-bottom:12px;">
                        <?= Form::input('amount', 'Montant à ajouter (XOF)', '', [
                            'type' => 'number',
                            'required' => true,
                            'min' => 1
                        ]) ?>
                    </div>
                     <div style="margin-bottom:15px;">
                        <?= Form::input('justification', 'Justification', '', [
                            'required' => true,
                            'placeholder' => 'ex: Approvisionnement de banque'
                        ]) ?>
                    </div>

                    <?= Ui::button('Confirmer l\'appro', ['type' => 'submit', 'variant' => 'primary', 'style' => 'width:100%;']) ?>
                </form>

                <!-- Dépense Exceptionnelle (Décaissement direct) -->
                <form method="POST" action="<?= View::url('finance/caisse/decaissement') ?>" class="finea-section-card">
                    <?= Csrf::input() ?>
                    <h3 class="finea-section-title" style="color:var(--finea-danger); margin-bottom:15px;">Décaissement direct</h3>
                    <p style="font-size:0.85rem; color:var(--finea-muted); margin-bottom:15px;">
                        Enregistrer une dépense directe ou achat en espèces (ex: fournitures exceptionnelles, carburant urgent...).
                    </p>

                    <div style="margin-bottom:12px;">
                        <?= Form::input('amount', 'Montant à décaisser (XOF)', '', [
                            'type' => 'number',
                            'required' => true,
                            'min' => 1
                        ]) ?>
                    </div>
                    <div style="margin-bottom:15px;">
                        <?= Form::input('justification', 'Justification', '', [
                            'required' => true,
                            'placeholder' => 'ex: Carburant coursier agence'
                        ]) ?>
                    </div>

                    <?= Ui::button('Confirmer le décaissement', ['type' => 'submit', 'variant' => 'danger', 'style' => 'width:100%;']) ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
