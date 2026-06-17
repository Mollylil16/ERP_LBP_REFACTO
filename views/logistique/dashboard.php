<?php
/** @var array $kpis */
use App\Helpers\View;
use App\View\Components\Ui;

ob_start();
require __DIR__ . '/_navigation.php';
?>

<?= Ui::pageHeader('Module Logistique Interne', 'Tableau de bord', [
    'actions' => '<div style="display:flex; gap:.5rem;">'
        . Ui::button('Nouveau prestataire', 'logistique/prestataires/nouveau', ['variant' => 'outline'])
        . Ui::button('Nouvelle facture', 'logistique/factures/nouvelle', ['variant' => 'primary'])
        . '</div>'
]) ?>

<!-- KPIs -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:2rem;">
    <div class="kpi-card" style="border-left:4px solid #ef4444;">
        <span style="font-size:.78rem; font-weight:600; text-transform:uppercase; color:#ef4444;">Encours Fournisseurs</span>
        <strong style="font-size:1.8rem; color:#ef4444; display:block;"><?= number_format($kpis['encours_fournisseurs'], 0, ',', ' ') ?> XOF</strong>
        <small style="color:var(--color-muted);">Factures non réglées</small>
    </div>
    <div class="kpi-card" style="border-left:4px solid #f59e0b;">
        <span style="font-size:.78rem; font-weight:600; text-transform:uppercase; color:#f59e0b;">Retraits en attente</span>
        <strong style="font-size:2.2rem; color:#f59e0b; display:block;"><?= $kpis['retraits_en_attente'] ?></strong>
        <small style="color:var(--color-muted);">Approbation requise</small>
    </div>
    <div class="kpi-card" style="border-left:4px solid #3b82f6;">
        <span style="font-size:.78rem; font-weight:600; text-transform:uppercase; color:#3b82f6;">Fournitures en attente</span>
        <strong style="font-size:2.2rem; color:#3b82f6; display:block;"><?= $kpis['fournitures_en_attente'] ?></strong>
        <small style="color:var(--color-muted);">Demandes à valider</small>
    </div>
    <div class="kpi-card" style="border-left:4px solid #8b5cf6;">
        <span style="font-size:.78rem; font-weight:600; text-transform:uppercase; color:#8b5cf6;">Crédits inter-agences</span>
        <strong style="font-size:1.8rem; color:#8b5cf6; display:block;"><?= number_format($kpis['credits_inter_agences'], 0, ',', ' ') ?> XOF</strong>
        <small style="color:var(--color-muted);">Dettes en attente d'apurement</small>
    </div>
</div>

<!-- Actions rapides -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
    <section class="finea-section-card">
        <div class="finea-section-heading">
            <h2 class="finea-section-title">Actions rapides</h2>
        </div>
        <div class="action-list">
            <a href="<?= View::url('logistique/prestataires') ?>" class="action-item">
                <span class="material-icons action-icon">business</span>
                <div><strong>Prestataires</strong><span>Gestion des fournisseurs et partenaires</span></div>
            </a>
            <a href="<?= View::url('logistique/factures') ?>" class="action-item">
                <span class="material-icons action-icon">receipt_long</span>
                <div><strong>Factures Prestataires</strong><span>Saisie et suivi des factures</span></div>
            </a>
            <a href="<?= View::url('logistique/retraits') ?>" class="action-item">
                <span class="material-icons action-icon">account_balance</span>
                <div><strong>Retraits Hub</strong><span>Paiements depuis la caisse centrale</span></div>
            </a>
            <a href="<?= View::url('logistique/fournitures') ?>" class="action-item">
                <span class="material-icons action-icon">shopping_cart</span>
                <div><strong>Fournitures Agences</strong><span>Demandes et livraisons de consommables</span></div>
            </a>
            <a href="<?= View::url('logistique/credits') ?>" class="action-item">
                <span class="material-icons action-icon">swap_horiz</span>
                <div><strong>Crédits inter-agences</strong><span>Équilibrage des dettes entre agences</span></div>
            </a>
        </div>
    </section>

    <section class="finea-section-card">
        <div class="finea-section-heading">
            <h2 class="finea-section-title">Workflows clés</h2>
        </div>
        <div style="padding:.5rem 0;">
            <?php $flows = [
                ['Prestataire → Facture', '#7c3aed', 'Enregistrer la facture reçue'],
                ['Facture → Retrait Hub', '#ef4444', 'Demander le décaissement de la caisse'],
                ['Retrait → Approbation', '#f59e0b', 'Validation par la caissière principale'],
                ['Approbation → Paiement', '#10b981', 'Facture mise à jour comme PAYÉE'],
            ];
            foreach ($flows as $i => [$step, $color, $desc]): ?>
            <div style="display:flex; align-items:center; gap:.75rem; padding:.4rem 0; border-bottom:1px solid var(--color-border,#e5e7eb);">
                <span style="width:22px; height:22px; border-radius:50%; background:<?= $color ?>; color:white; display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; flex-shrink:0;"><?= $i+1 ?></span>
                <div>
                    <strong style="font-size:.85rem;"><?= $step ?></strong>
                    <span style="font-size:.78rem; color:#6b7280; margin-left:.5rem;"><?= $desc ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
