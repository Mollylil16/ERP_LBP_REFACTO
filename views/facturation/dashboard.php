<?php

declare(strict_types=1);

// 'href' tag for unit testing constraints
use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Pages\Facturation\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 */

?>
<div class="finea-shell facturation-dashboard">
    <div class="finea-container">
        
        <?= Dashboard::header(
            $dashboardModule['label'],
            $dashboardModule['description'],
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
                    <?= App\View\Components\Ui::emptyState('Bientôt', 'Historique des factures et proformas générés.') ?>
                </div>

            </div>

            <!-- Colonne Latérale (Actions Rapides) -->
            <div class="rh-dashboard-side">
                <?= Dashboard::actions($page->quickActions, [
                    'title' => 'Actions de Facturation',
                    'class' => 'finea-section-card',
                ]) ?>
            </div>
        </div>

    </div>
</div>
