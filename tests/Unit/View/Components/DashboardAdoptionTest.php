<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class DashboardAdoptionTest extends TestCase
{
    public function test_module_dashboards_use_shared_dashboard_component(): void
    {
        foreach ($this->dashboardViews() as $file) {
            $source = file_get_contents(BASE_PATH . '/' . $file);
            self::assertStringContainsString('Components\\Dashboard', (string) $source, $file);
            self::assertTrue(
                str_contains((string) $source, 'Dashboard::kpis')
                || str_contains((string) $source, 'Dashboard::businessModuleDashboard'),
                $file . ' doit utiliser les composants Dashboard.'
            );
        }
    }

    /** @return array<int, string> */
    private function dashboardViews(): array
    {
        return [
            'views/finance/dashboard.php',
            'views/rh/dashboard.php',
            'views/admin/dashboard.php',
            'views/employee/dashboard.php',
            'views/colisage/dashboard.php',
            'views/logistique/dashboard.php',
            'views/crm/dashboard.php',
            'views/tickets/dashboard.php',
            'views/site_admin/dashboard.php',
            'views/transit_douane/dashboard.php',
            'views/tracking_colis/dashboard.php',
            'views/facturation/dashboard.php',
            'views/entrepots/dashboard.php',
            'views/flotte_transport/dashboard.php',
            'views/portefeuille_clients/dashboard.php',
            'views/agents_correspondants/dashboard.php',
            'views/pilotage_dg/dashboard.php',
            'views/dashboard/index.php',
        ];
    }
}
