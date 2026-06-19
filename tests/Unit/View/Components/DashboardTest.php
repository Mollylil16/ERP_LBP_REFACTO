<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\Dashboard;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    public function test_renders_escaped_kpis(): void
    {
        $html = Dashboard::kpis([['label' => '<Effectif>', 'value' => 12, 'meta' => 'Actifs']]);
        self::assertStringContainsString('&lt;Effectif&gt;', $html);
        self::assertStringContainsString('12', $html);
        self::assertStringContainsString('finea-kpi-grid', $html);
    }

    public function test_renders_dashboard_actions_with_application_urls(): void
    {
        $html = Dashboard::actions([['label' => 'Ouvrir', 'hint' => 'Accès rapide', 'url' => '/rh']]);
        self::assertStringContainsString('/rh', $html);
        self::assertStringContainsString('Accès rapide', $html);
    }

    public function test_renders_clickable_accessible_kpi(): void
    {
        $html = Dashboard::kpi(['label' => 'Demandes employés', 'value' => 4, 'meta' => 'À traiter', 'href' => 'rh/cycle-vie?section=workflows']);
        self::assertStringStartsWith('<a ', $html);
        self::assertStringContainsString('is-clickable', $html);
        self::assertStringContainsString('aria-label="Ouvrir : Demandes employés"', $html);
        self::assertStringContainsString('section=workflows', $html);
    }

    public function test_renders_clickable_alert_cards(): void
    {
        $html = Dashboard::alerts([[
            'label' => 'Demandes employés', 'count' => 3, 'description' => 'À traiter',
            'tone' => 'success', 'href' => 'rh/cycle-vie?section=workflows',
        ]]);
        self::assertStringContainsString('<a class="rh-alert-card', $html);
        self::assertStringContainsString('section=workflows', $html);
    }

    public function test_renders_rh_dashboard_tabs_with_expected_classes(): void
    {
        $html = Dashboard::tabs([
            [
                'key' => 'classic',
                'label' => 'Classique',
                'description' => 'Vue principale',
                'href' => 'rh/dashboard',
            ],
        ], 'classic');

        self::assertStringContainsString('rh-dashboard-tabs', $html);
        self::assertStringContainsString('class="rh-dashboard-tab is-active"', $html);
        self::assertStringNotContainsString('<span><strong>Classique', $html);
    }

    public function test_renders_distribution_and_quick_actions(): void
    {
        $html = Dashboard::distributionWithActions(
            [['label' => 'Informatique', 'total' => 2]],
            4,
            [['label' => 'Voir le personnel', 'href' => 'rh/personnel']]
        );

        self::assertStringContainsString('width: 50%', $html);
        self::assertStringContainsString('rh-quick-card', $html);
        self::assertStringContainsString('Voir le personnel', $html);
    }

    public function test_renders_disabled_report_action(): void
    {
        $html = Dashboard::reports([[
            'title' => 'Rapport effectifs',
            'description' => 'Description',
            'action' => 'Exporter',
            'button' => ['variant' => 'secondary', 'disabled' => true],
        ]]);

        self::assertStringContainsString('rh-report-grid', $html);
        self::assertStringContainsString(' disabled>', $html);
    }
}
