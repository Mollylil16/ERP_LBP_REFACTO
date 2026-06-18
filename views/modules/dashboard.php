<?php

use App\Helpers\View;
use App\Helpers\ModuleIcon;
use App\View\Components\Dashboard;

/** @var array<string, mixed> $dashboardModule */
$module = $dashboardModule;
$module['kpis'] = array_map(
    static fn(array $kpi): array => $kpi + ['href' => '/' . $module['slug'] . '/dashboard#operations'],
    $module['kpis']
);

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

        <?= Dashboard::kpis($module['kpis'], ['class' => 'module-dashboard-kpis']) ?>

        <div class="module-dashboard-grid" id="operations">
            <section class="finea-section-card">
                <div class="module-section-heading">
                    <div>
                        <p class="finea-eyebrow">Accès rapides</p>
                        <h2 class="finea-section-title">Opérations du module</h2>
                    </div>
                    <span class="finea-status-badge finea-status-badge--info">Socle clean code</span>
                </div>
                <?= Dashboard::actions($module['actions']) ?>
            </section>

            <aside class="finea-section-card module-identity-card">
                <span class="module-dashboard-icon large"><?= ModuleIcon::svg((string) $module['iconKey']) ?></span>
                <h2><?= View::e((string) $module['label']) ?></h2>
                <p>Les couleurs, l’icône et le code reprennent le point d’entrée du portail pour identifier immédiatement l’espace courant.</p>
                <div class="module-identity-swatches">
                    <span style="background: <?= View::e((string) $module['accent2']) ?>"></span>
                    <span style="background: <?= View::e((string) $module['accent']) ?>"></span>
                    <span style="background: var(--finea-gold)"></span>
                </div>
            </aside>
        </div>

        <section class="finea-section-card">
            <div class="module-section-heading">
                <div>
                    <p class="finea-eyebrow">Backend</p>
                    <h2 class="finea-section-title">Structure prévue pour l’évolution métier</h2>
                </div>
            </div>
            <div class="module-workflow-grid">
                <?php foreach ($module['workflow'] as $step): ?>
                    <article>
                        <strong><?= View::e((string) $step['title']) ?></strong>
                        <p><?= View::e((string) $step['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
