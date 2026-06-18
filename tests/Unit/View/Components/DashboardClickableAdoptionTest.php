<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class DashboardClickableAdoptionTest extends TestCase
{
    public function test_all_main_dashboards_define_kpi_destinations(): void
    {
        foreach ($this->dashboardViews() as $file) {
            $source = (string) file_get_contents(BASE_PATH . '/' . $file);
            self::assertTrue(
                str_contains($source, "'href'")
                || str_contains($source, 'Dashboard::businessModuleDashboard'),
                $file . ' doit exposer des KPI ou actions cliquables via les composants.'
            );
        }
    }

    public function test_lifecycle_creation_forms_are_opened_through_modal_component(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/views/rh/lifecycle/index.php');
        foreach (['rh-contract-form', 'rh-assignment-form', 'rh-evaluation-form', 'rh-training-form', 'rh-discipline-form'] as $id) {
            self::assertStringContainsString("Modal::render('{$id}'", $source);
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
