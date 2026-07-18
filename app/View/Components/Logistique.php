<?php

declare(strict_types=1);

namespace App\View\Components;

use App\View\Components\Dashboard;
use App\View\Pages\Logistique\DashboardPage;

final class Logistique
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
        $actions = Dashboard::actions($page->quickActions, [
            'title' => 'Actions Logistiques',
            'class' => 'finea-section-card',
        ]);

        return '<div class="finea-shell logistique-dashboard">'
            . '<div class="finea-container">'
            . $header
            . '<div class="rh-dashboard-grid" style="margin-top: 2rem;">'
            . '<div class="rh-dashboard-main">'
            . $kpis
            . '<div class="finea-empty-state" style="margin-top: 2rem;">'
            . 'Bientôt : Suivi des mouvements et incidents logistiques.'
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
