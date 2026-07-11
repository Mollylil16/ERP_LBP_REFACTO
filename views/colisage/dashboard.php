<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Components\Colisage;
use App\View\Pages\Colisage\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 */

View::startSection('content'); ?>

<div class="finea-shell colisage-dashboard">
    <div class="finea-container">
        
        <?= Dashboard::header(
            $dashboardModule['label'],
            "Le module colisage orchestre la réception en agence, le groupage des manifestes, le transport et les retraits de colis.",
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
                    <h3>Réseau des Agences Actives</h3>
                    <p style="color: #64748b; font-size: 0.95rem; margin-top: 0.2rem;">Suivi de l'activité par point de vente / agence d'expédition.</p>
                    <?= Colisage::agencesOverview() ?>
                </div>

                <div style="margin-top: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                        <h3>Derniers Colis Enregistrés</h3>
                        <?= Colisage::recentParcels($page->recentParcels) ?>
                    </div>
                    <div>
                        <h3>Dernières Expéditions (Groupage)</h3>
                        <?= Colisage::recentExpeditions($page->recentExpeditions) ?>
                    </div>
                </div>

            </div>

            <!-- Colonne Latérale (Actions Rapides) -->
            <div class="rh-dashboard-side">
                <?= Dashboard::actions($page->quickActions, [
                    'title' => 'Raccourcis Opérationnels',
                    'class' => 'finea-section-card',
                ]) ?>
            </div>
        </div>

    </div>
</div>

<?php View::endSection(); ?>
