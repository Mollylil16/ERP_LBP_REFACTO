<?php

declare(strict_types=1);

// 'href' tag for unit testing constraints
use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Components\Finance;
use App\View\Pages\Finance\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 */

// Basic styling adjustments for specific layout if needed
?>
<style>
    .module-section-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 1rem;
    }
    .finea-eyebrow {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
</style>

<div class="finea-shell">
    <div class="finea-container">
        
        <?= Dashboard::header(
            $dashboardModule['label'],
            "Vue d'ensemble des flux financiers, facturation et états de caisse.",
            [
                'eyebrow' => $dashboardModule['code'] . ' Dashboard',
                'class' => 'rh-hero-white'
            ]
        ) ?>

        <div class="rh-dashboard-grid" style="margin-top: 2rem;">
            <!-- Colonne Principale -->
            <div class="rh-dashboard-main">
                
                <?= Dashboard::kpis($page->kpis) ?>

                <div style="margin-top: 2rem;">
                    <?= Finance::recentFactures($page->recentFactures) ?>
                </div>

                <div style="margin-top: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <?= Finance::recentEcritures($page->recentEcritures) ?>
                    <?= Finance::recentEtats($page->recentEtats) ?>
                </div>

            </div>

            <!-- Colonne Latérale (Actions Rapides) -->
            <div class="rh-dashboard-side">
                <?= Dashboard::actions($page->quickActions, [
                    'title' => 'Actions Financières',
                    'class' => 'finea-section-card',
                ]) ?>
            </div>
        </div>

    </div>
</div>
