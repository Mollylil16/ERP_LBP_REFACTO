<?php

declare(strict_types=1);

namespace App\View\Components;

use App\View\Components\Dashboard;
use App\View\Pages\Facturation\DashboardPage;

final class Facturation
{
    public static function dashboardPage(DashboardPage $page, array $dashboardModule): string
    {
        $header = Dashboard::header(
            $dashboardModule['label'],
            $dashboardModule['description'],
            [
                'eyebrow' => $dashboardModule['code'] . ' Dashboard',
                'class' => 'rh-hero-white'
            ]
        );

        $kpis = Dashboard::kpis($page->kpis);
        $empty = \App\View\Components\Ui::emptyState('Bientôt', 'Historique des factures et proformas générés.');
        $actions = Dashboard::actions($page->quickActions, [
            'title' => 'Actions de Facturation',
            'class' => 'finea-section-card',
        ]);

        return '<div class="finea-shell facturation-dashboard">'
            . '<div class="finea-container">'
            . $header
            . '<div class="rh-dashboard-grid" style="margin-top: 2rem;">'
            . '<div class="rh-dashboard-main">'
            . $kpis
            . '<div style="margin-top: 2rem;">'
            . $empty
            . '</div>'
            . '</div>'
            . '<div class="rh-dashboard-side">'
            . $actions
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
