<?php
/** @var array $counts */
use App\Helpers\View;
use App\View\Components\Ui;

ob_start();
require __DIR__ . '/_navigation.php';
?>

<?= Ui::pageHeader('Module Colisage & Fret', 'Tableau de bord', [
    'actions' => '<div style="display:flex; gap:.5rem;">'
        . Ui::button('Nouveau Colis', 'colisage/colis/nouveau', ['variant' => 'primary'])
        . Ui::button('Nouvelle Expédition', 'colisage/expeditions/nouveau', ['variant' => 'outline'])
        . '</div>'
]) ?>

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 2rem;">
    <?php
    $statuses = [
        'RECEPTIONNE'   => ['label' => 'Réceptionnés',    'color' => '#f59e0b', 'icon' => 'inbox'],
        'EN_PREPARATION'=> ['label' => 'En préparation',  'color' => '#3b82f6', 'icon' => 'inventory'],
        'EN_TRANSIT'    => ['label' => 'En transit',      'color' => '#8b5cf6', 'icon' => 'local_shipping'],
        'ARRIVE'        => ['label' => 'Arrivés',         'color' => '#10b981', 'icon' => 'location_on'],
        'RETIRE'        => ['label' => 'Retirés',         'color' => '#6b7280', 'icon' => 'check_circle'],
    ];
    foreach ($statuses as $key => $info):
        $val = $counts[$key] ?? 0;
    ?>
    <article class="kpi-card" style="border-left: 4px solid <?= $info['color'] ?>;">
        <div style="display:flex; align-items:center; gap:.5rem; color:<?= $info['color'] ?>;">
            <span class="material-icons"><?= $info['icon'] ?></span>
            <span style="font-size:.8rem; font-weight:600; text-transform:uppercase;"><?= $info['label'] ?></span>
        </div>
        <strong style="font-size: 2.2rem; font-weight:800; color:<?= $info['color'] ?>; display:block; margin-top:.25rem;">
            <?= $val ?>
        </strong>
    </article>
    <?php endforeach; ?>
</div>

<!-- Accès rapides -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
    <section class="finea-section-card">
        <div class="finea-section-heading">
            <h2 class="finea-section-title">Actions rapides</h2>
        </div>
        <div class="action-list">
            <a href="<?= View::url('colisage/colis/nouveau') ?>" class="action-item">
                <span class="material-icons action-icon">add_box</span>
                <div>
                    <strong>Nouveau Colis</strong>
                    <span>Enregistrer une nouvelle réception</span>
                </div>
            </a>
            <a href="<?= View::url('colisage/expeditions/nouveau') ?>" class="action-item">
                <span class="material-icons action-icon">local_shipping</span>
                <div>
                    <strong>Nouvelle Expédition</strong>
                    <span>Créer un manifeste de groupage</span>
                </div>
            </a>
            <a href="<?= View::url('colisage/colis?status=ARRIVE') ?>" class="action-item">
                <span class="material-icons action-icon">location_on</span>
                <div>
                    <strong>Colis arrivés</strong>
                    <span>Colis disponibles au retrait</span>
                </div>
            </a>
            <a href="<?= View::url('colisage/inventaire/nouveau') ?>" class="action-item">
                <span class="material-icons action-icon">fact_check</span>
                <div>
                    <strong>Nouvel Inventaire</strong>
                    <span>Démarrer une campagne d'inventaire</span>
                </div>
            </a>
        </div>
    </section>

    <section class="finea-section-card">
        <div class="finea-section-heading">
            <h2 class="finea-section-title">Cycle de vie d'un colis</h2>
        </div>
        <div style="padding: .5rem 0;">
            <?php $steps = [
                ['RÉCEPTIONNÉ', '#f59e0b', 'Colis reçu en agence départ'],
                ['EN PRÉPARATION', '#3b82f6', 'Assigné à une expédition'],
                ['EN TRANSIT', '#8b5cf6', 'En cours de transport'],
                ['ARRIVÉ', '#10b981', 'Reçu en agence destination'],
                ['RETIRÉ', '#6b7280', 'Remis au destinataire final'],
            ];
            foreach ($steps as [$step, $color, $desc]): ?>
            <div style="display:flex; align-items:center; gap:.75rem; padding:.4rem 0; border-bottom: 1px solid var(--color-border, #e5e7eb);">
                <span style="width:12px; height:12px; border-radius:50%; background:<?= $color ?>; flex-shrink:0;"></span>
                <div>
                    <strong style="font-size:.8rem; color:<?= $color ?>;"><?= $step ?></strong>
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
