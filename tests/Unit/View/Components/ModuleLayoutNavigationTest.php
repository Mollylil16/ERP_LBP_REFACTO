<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class ModuleLayoutNavigationTest extends TestCase
{
    public function test_back_link_is_outside_scrollable_navigation_zone(): void
    {
        $source = file_get_contents(BASE_PATH . '/views/layouts/module.php');
        self::assertIsString($source);
        $scrollPosition = strpos($source, 'Navigation::module');
        $backPosition = strpos($source, 'module-back-link');
        self::assertNotFalse($scrollPosition);
        self::assertNotFalse($backPosition);
        self::assertGreaterThan($scrollPosition, $backPosition);
    }
}
