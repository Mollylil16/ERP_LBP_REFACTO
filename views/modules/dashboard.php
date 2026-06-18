<?php

use App\Helpers\ModuleIcon;
use App\View\Components\Dashboard;
use App\View\Components\Ui;

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
        <?= Ui::pageHeader(
            (string) $module['label'],
            (string) $module['description'],
            [
                'eyebrow' => (string) $module['code'] . ' • Module métier',
                'eyebrow_class' => 'finea-eyebrow',
                'class' => 'module-dashboard-hero',
                'style' => '--module-hero-gradient: ' . (string) $module['gradient'] . ';',
                'icon' => ModuleIcon::svg((string) $module['iconKey']),
                'badge' => '<span class="module-dashboard-chip">Dashboard prêt</span>',
                'actions' => Ui::button('Changer de module', [
                    'href' => 'selection_portail',
                    'variant' => 'accent',
                ]),
            ]
        ) ?>

        <?= Dashboard::kpis($module['kpis'], ['class' => 'module-dashboard-kpis']) ?>

        <div class="module-dashboard-grid" id="operations">
            <?= Dashboard::moduleOperations($module['actions']) ?>
            <?= Dashboard::moduleIdentity($module) ?>
        </div>

        <?= Dashboard::moduleWorkflowSection($module['workflow']) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
