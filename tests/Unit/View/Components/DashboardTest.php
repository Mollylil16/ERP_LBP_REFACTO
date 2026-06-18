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
}
