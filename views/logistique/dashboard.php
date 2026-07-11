<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Pages\Logistique\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 */

View::startSection('content'); ?>

<div class="finea-shell logistique-dashboard">
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

                <div class="finea-empty-state" style="margin-top: 2rem;">
                    Bientôt : Suivi des mouvements et incidents logistiques.
                </div>

            </div>

            <!-- Colonne Latérale (Actions Rapides) -->
            <div class="rh-dashboard-side">
                <?= Dashboard::actions($page->quickActions, [
                    'title' => 'Actions Logistiques',
                    'class' => 'finea-section-card',
                ]) ?>
            </div>
        </div>

    </div>
</div>

<?php View::endSection(); ?>
