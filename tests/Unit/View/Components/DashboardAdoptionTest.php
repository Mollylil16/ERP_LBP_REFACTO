<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class DashboardAdoptionTest extends TestCase
{
    public function test_module_dashboards_use_shared_dashboard_component(): void
    {
        foreach ([
            'views/modules/dashboard.php',
            'views/rh/dashboard.php',
            'views/admin/dashboard.php',
            'views/employee/dashboard.php',
            'views/dashboard/index.php',
        ] as $file) {
            $source = file_get_contents(BASE_PATH . '/' . $file);
            self::assertStringContainsString('Components\\Dashboard', (string) $source, $file);
            self::assertStringContainsString('Dashboard::kpis', (string) $source, $file);
        }
    }
}
