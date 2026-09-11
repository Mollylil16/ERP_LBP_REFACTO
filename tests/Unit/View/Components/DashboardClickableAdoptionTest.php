<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class DashboardClickableAdoptionTest extends TestCase
{
    use ResoudLesComposantsDeVue;

    public function test_all_main_dashboards_define_kpi_destinations(): void
    {
        foreach ($this->dashboardViews() as $file) {
            $source = $this->sourceAvecComposants($file);

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
}
