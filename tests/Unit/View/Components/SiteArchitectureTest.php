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
        foreach (glob(BASE_PATH . '/views/site_admin/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringContainsString('$page', $source, $file);
            self::assertStringNotContainsString('ViewBag::from', $source, $file);
        }
        self::assertFileDoesNotExist(BASE_PATH . '/views/site_admin/_navigation.php');
    }

    public function test_site_supports_branding_carousel_marketplace_and_forum_components(): void
    {
        $site = (string) file_get_contents(BASE_PATH . '/app/View/Components/Site.php');
        $admin = (string) file_get_contents(BASE_PATH . '/app/View/Components/SiteAdmin.php');
        $form = (string) file_get_contents(BASE_PATH . '/app/View/Components/Form.php');

        foreach (['function carousel(', 'function products(', 'function topics('] as $method) {
            self::assertStringContainsString($method, $site);
        }
        self::assertStringContainsString('function configuration(', $admin);
        self::assertStringContainsString('function colorPalette(', $form);
    }
}
