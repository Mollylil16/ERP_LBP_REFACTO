<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class EmployeeArchitectureTest extends TestCase
{
    public function test_employee_views_use_page_objects_and_no_navigation_partial(): void
    {
        foreach (glob(BASE_PATH . '/views/employee/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringContainsString('$page', $source, $file);
            self::assertStringNotContainsString('ViewBag::from', $source, $file);
            self::assertStringNotContainsString('_navigation.php', $source, $file);
        }
        self::assertFileDoesNotExist(BASE_PATH . '/views/employee/_navigation.php');
        $controller = (string) file_get_contents(BASE_PATH . '/app/Controllers/Employee/EmployeePortalController.php');
        self::assertStringContainsString('extends EmployeeBaseController', $controller);
    }
}
