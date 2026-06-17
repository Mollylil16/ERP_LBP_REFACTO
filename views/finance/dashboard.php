<?php
/** @var array $caisse */
/** @var array $mouvements */
/** @var float $unpaidClient */
/** @var float $unpaidPrestataires */
/** @var int $invoicesCount */

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Ui;

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Finance & Trésorerie',
            'Tableau de bord',
            'Suivi des flux financiers d’agence, des caisses physiques et des encaissements/décaissements.',
            $caisse['status'] === 'OUVERTE' 
                ? Ui::badge('Caisse Ouverte', 'success') 
                : Ui::badge('Caisse Fermée', 'danger'),
            ['class' => 'rh-hero']
        ) ?>

        <section class="finea-grid finea-kpi-grid" style="margin-top: 24px;">
            <article class="finea-kpi-card">
                <span class="finea-kpi-label">Solde de Caisse</span>
                <strong class="finea-kpi-value" style="color:var(--finea-primary);"><?= number_format((float)$caisse['balance'], 2, ',', ' ') ?> XOF</strong>
                <small class="finea-kpi-meta">Espèces disponibles en agence</small>
            </article>

            <article class="finea-kpi-card">
                <span class="finea-kpi-label">Encours Clients</span>
                <strong class="finea-kpi-value" style="color:var(--finea-warning);"><?= number_format($unpaidClient, 2, ',', ' ') ?> XOF</strong>
                <small class="finea-kpi-meta">Factures clients à recouvrer</small>
            </article>

            <article class="finea-kpi-card">
                <span class="finea-kpi-label">Dettes Prestataires</span>
                <strong class="finea-kpi-value" style="color:var(--finea-danger);"><?= number_format($unpaidPrestataires, 2, ',', ' ') ?> XOF</strong>
                <small class="finea-kpi-meta">Factures partenaires à régler</small>
            </article>
        </section>

        <div class="rh-dashboard-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:24px; margin-top:24px;">
            <div style="display:flex; flex-direction:column; gap:24px;">
                <!-- Mouvements Récents -->
                <section class="finea-section-card">
                    <div class="module-section-heading" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <div>
                            <p class="finea-eyebrow">Trésorerie</p>
                            <h2 class="finea-section-title">Mouvements de Caisse récents</h2>
                        </div>
                        <a href="<?= View::url('finance/caisse') ?>" class="finea-action-btn finea-action-btn--secondary" style="font-size:0.85rem;">Voir tout le journal</a>
                    </div>

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
                                        <td colspan="5" style="text-align:center; padding:20px; color:var(--finea-muted);">Aucun mouvement enregistré.</td>
                                    </tr>
                                <?php else: foreach ($mouvements as $m): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                                        <td>
                                            <?php if ($m['type'] === 'ENTREE' || $m['type'] === 'APPRO'): ?>
                                                <?= Ui::badge($m['type'], 'success') ?>
                                            <?php else: ?>
                                                <?= Ui::badge($m['type'], 'danger') ?>
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

            <!-- Actions Rapides -->
            <aside class="finea-section-card">
                <h3 class="finea-section-title" style="margin-bottom:15px;">Actions rapides</h3>
                <div class="module-action-list" style="display:flex; flex-direction:column; gap:10px;">
                    <a href="<?= View::url('finance/factures') ?>" class="finea-action-btn" style="text-align:left; justify-content:flex-start; padding:15px; width:100%; border:1px solid var(--finea-border); border-radius:8px; display:block; text-decoration:none; color:inherit;">
                        <strong style="display:block; font-size:1rem; color:var(--finea-primary);">Enregistrer encaissement</strong>
                        <span style="font-size:0.8rem; color:var(--finea-muted);">Saisir un versement client</span>
                    </a>
                    
                    <a href="<?= View::url('finance/decaissements') ?>" class="finea-action-btn" style="text-align:left; justify-content:flex-start; padding:15px; width:100%; border:1px solid var(--finea-border); border-radius:8px; display:block; text-decoration:none; color:inherit;">
                        <strong style="display:block; font-size:1rem; color:var(--finea-danger);">Payer un prestataire</strong>
                        <span style="font-size:0.8rem; color:var(--finea-muted);">Décaisser des fonds pour règlement</span>
                    </a>

                    <a href="<?= View::url('finance/credits/nouveau') ?>" class="finea-action-btn" style="text-align:left; justify-content:flex-start; padding:15px; width:100%; border:1px solid var(--finea-border); border-radius:8px; display:block; text-decoration:none; color:inherit;">
                        <strong style="display:block; font-size:1rem; color:var(--finea-warning);">Crédit Inter-Agence</strong>
                        <span style="font-size:0.8rem; color:var(--finea-muted);">Effectuer un transfert entre agences</span>
                    </a>

                    <a href="<?= View::url('finance/clotures') ?>" class="finea-action-btn" style="text-align:left; justify-content:flex-start; padding:15px; width:100%; border:1px solid var(--finea-border); border-radius:8px; display:block; text-decoration:none; color:inherit;">
                        <strong style="display:block; font-size:1rem; color:var(--finea-muted);">Faire le Point de caisse</strong>
                        <span style="font-size:0.8rem; color:var(--finea-muted);">Soumettre ou valider la clôture</span>
                    </a>
                </div>
            </aside>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
