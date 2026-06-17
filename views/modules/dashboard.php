<?php

use App\Helpers\View;
use App\Helpers\ModuleIcon;

/** @var array<string, mixed> $dashboardModule */
$module = $dashboardModule;

ob_start();
?>
<div class="finea-shell module-dashboard-shell">
    <div class="finea-container">
        <section class="finea-page-header module-dashboard-hero" style="--module-hero-gradient: <?= View::e($module['gradient']) ?>;">
            <div class="module-dashboard-title">
                <span class="module-dashboard-icon"><?= ModuleIcon::svg((string) $module['iconKey']) ?></span>
                <div>
                    <p class="finea-eyebrow"><?= View::e((string) $module['code']) ?> • Module métier</p>
                    <h1><?= View::e((string) $module['label']) ?></h1>
                    <p><?= View::e((string) $module['description']) ?></p>
                </div>
            </div>
            <div class="finea-header-actions">
                <span class="module-dashboard-chip">Dashboard prêt</span>
                <a href="<?= View::url('selection_portail') ?>" class="finea-action-btn finea-action-btn--accent">Changer de module</a>
            </div>
        </section>

        <section class="finea-grid finea-kpi-grid">
            <?php foreach ($module['kpis'] as $kpi): ?>
                <article class="finea-kpi-card module-accent-card">
                    <span class="finea-kpi-label"><?= View::e((string) $kpi['label']) ?></span>
                    <strong class="finea-kpi-value"><?= View::e((string) $kpi['value']) ?></strong>
                    <small class="finea-kpi-meta"><?= View::e((string) $kpi['meta']) ?></small>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="module-dashboard-grid">
            <section class="finea-section-card">
                <div class="module-section-heading">
                    <div>
                        <p class="finea-eyebrow">Accès rapides</p>
                        <h2 class="finea-section-title">Opérations du module</h2>
                    </div>
                    <span class="finea-status-badge finea-status-badge--info">Opérationnel</span>
                </div>
                <div class="module-action-list">
                    <?php foreach ($module['actions'] as $action): ?>
                        <a href="<?= View::url((string) $action['url']) ?>">
                            <strong><?= View::e((string) $action['label']) ?></strong>
                            <span><?= View::e((string) $action['hint']) ?></span>
                            <small>Ouvrir</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="finea-section-card module-identity-card">
                <span class="module-dashboard-icon large"><?= ModuleIcon::svg((string) $module['iconKey']) ?></span>
                <h2><?= View::e((string) $module['label']) ?></h2>
                <p><?= View::e((string) $module['description']) ?></p>
                <div class="module-identity-swatches">
                    <span style="background: <?= View::e((string) $module['accent2']) ?>"></span>
                    <span style="background: <?= View::e((string) $module['accent']) ?>"></span>
                    <span style="background: var(--finea-gold)"></span>
                </div>
            </aside>
        </div>

        
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
