<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class SiteArchitectureTest extends TestCase
{
    public function test_site_views_receive_page_objects(): void
    {
        foreach (glob(BASE_PATH . '/views/site/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringContainsString('$page', $source, $file);
            self::assertStringNotContainsString('ViewBag::from', $source, $file);
            self::assertStringNotContainsString('get_defined_vars', $source, $file);
        }
        self::assertFileDoesNotExist(BASE_PATH . '/views/site_admin/_navigation.php');
    }
}
