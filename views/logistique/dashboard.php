<?php
/** @var array $kpis */
use App\Helpers\View;
use App\View\Components\Ui;

ob_start();
require __DIR__ . '/_navigation.php';
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Module Logistique Interne', 'Tableau de bord', [
            'actions' => '<div style="display:flex; gap:.5rem;">'
                . Ui::button('Nouveau prestataire', 'logistique/prestataires/nouveau', ['variant' => 'secondary'])
                . Ui::button('Nouvelle facture', 'logistique/factures/nouvelle', ['variant' => 'primary'])
                . '</div>'
        ]) ?>

        <!-- KPIs -->
        <section class="finea-grid finea-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-top: 1.5rem; margin-bottom: 2rem;">
            <article class="finea-kpi-card" style="border-left: 4px solid var(--finea-danger); min-height: 110px;">
                <div>
                    <span class="finea-kpi-label" style="color: var(--finea-danger); text-transform: uppercase; font-size: 0.75rem; font-weight: 700;">Encours Fournisseurs</span>
                </div>
                <strong class="finea-kpi-value" style="color: var(--finea-danger); font-size: 1.8rem; margin-top: 0.5rem; display: block;">
                    <?= number_format($kpis['encours_fournisseurs'], 0, ',', ' ') ?> XOF
                </strong>
                <small style="color: var(--finea-muted); display: block; margin-top: 4px;">Factures non réglées</small>
            </article>

            <article class="finea-kpi-card" style="border-left: 4px solid var(--finea-warning); min-height: 110px;">
                <div>
                    <span class="finea-kpi-label" style="color: var(--finea-warning); text-transform: uppercase; font-size: 0.75rem; font-weight: 700;">Retraits en attente</span>
                </div>
                <strong class="finea-kpi-value" style="color: var(--finea-warning); font-size: 2.2rem; margin-top: 0.5rem; display: block;">
                    <?= $kpis['retraits_en_attente'] ?>
                </strong>
                <small style="color: var(--finea-muted); display: block; margin-top: 4px;">Approbation requise</small>
            </article>

            <article class="finea-kpi-card" style="border-left: 4px solid var(--finea-info); min-height: 110px;">
                <div>
                    <span class="finea-kpi-label" style="color: var(--finea-info); text-transform: uppercase; font-size: 0.75rem; font-weight: 700;">Fournitures en attente</span>
                </div>
                <strong class="finea-kpi-value" style="color: var(--finea-info); font-size: 2.2rem; margin-top: 0.5rem; display: block;">
                    <?= $kpis['fournitures_en_attente'] ?>
                </strong>
                <small style="color: var(--finea-muted); display: block; margin-top: 4px;">Demandes à valider</small>
            </article>

        </section>
 
        <!-- Actions rapides & Workflows -->
        <div class="finea-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <section class="finea-section-card">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Actions rapides</h2>
                </div>
                <div class="module-action-list">
                    <a href="<?= View::url('logistique/prestataires') ?>">
                        <strong>Prestataires</strong>
                        <span>Gestion des fournisseurs et partenaires</span>
                        <small>Ouvrir</small>
                    </a>
                    <a href="<?= View::url('logistique/factures') ?>">
                        <strong>Factures Prestataires</strong>
                        <span>Saisie et suivi des factures</span>
                        <small>Ouvrir</small>
                    </a>
                    <a href="<?= View::url('logistique/retraits') ?>">
                        <strong>Retraits Hub</strong>
                        <span>Paiements depuis la caisse centrale</span>
                        <small>Ouvrir</small>
                    </a>
                    <a href="<?= View::url('logistique/fournitures') ?>">
                        <strong>Fournitures Agences</strong>
                        <span>Demandes et livraisons de consommables</span>
                        <small>Ouvrir</small>
                    </a>
                </div>
            </section>

            <section class="finea-section-card">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Workflows clés</h2>
                </div>
                <div style="padding:.5rem 0; display: flex; flex-direction: column; gap: 12px;">
                    <?php $flows = [
                        ['Prestataire → Facture', 'var(--finea-primary)', 'Enregistrer la facture reçue'],
                        ['Facture → Retrait Hub', 'var(--finea-danger)', 'Demander le décaissement de la caisse'],
                        ['Retrait → Approbation', 'var(--finea-warning)', 'Validation par la caissière principale'],
                        ['Approbation → Paiement', 'var(--finea-success)', 'Facture mise à jour comme PAYÉE'],
                    ];
                    foreach ($flows as $i => [$step, $color, $desc]): ?>
                    <div style="display:flex; align-items:center; gap: 12px; padding: 12px; border: 1px solid var(--finea-border); border-radius: 12px; background: #fff;">
                        <span style="width:26px; height:26px; border-radius:50%; background:<?= $color ?>; color:white; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; flex-shrink:0;"><?= $i+1 ?></span>
                        <div>
                            <strong style="font-size:.9rem; color: var(--finea-navy); display: block;"><?= $step ?></strong>
                            <span style="font-size:.8rem; color: var(--finea-muted);"><?= $desc ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
