<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class DashboardClickableAdoptionTest extends TestCase
{
    public function test_all_main_dashboards_define_kpi_destinations(): void
    {
        foreach ([
            'views/rh/dashboard.php',
            'views/employee/dashboard.php',
            'views/admin/dashboard.php',
            'views/dashboard/index.php',
            'views/modules/dashboard.php',
        ] as $file) {
            self::assertStringContainsString("'href'", (string) file_get_contents(BASE_PATH . '/' . $file), $file);
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
