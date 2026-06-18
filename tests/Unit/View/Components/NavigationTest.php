<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\Navigation;
use InvalidArgumentException;
use Tests\TestCase;

final class NavigationTest extends TestCase
{
    public function test_groups_links_and_marks_active_item(): void
    {
        $html = Navigation::module([
            ['group' => 'Pilotage', 'key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => 'rh/dashboard'],
            ['group' => 'Talents', 'key' => 'training', 'label' => 'Formation', 'icon' => 'FO', 'url' => 'rh/formations'],
        ], 'training');
        self::assertStringContainsString('Pilotage', $html);
        self::assertStringContainsString('Talents', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringContainsString('data-nav-group-toggle', $html);
    }

    public function test_infers_groups_for_legacy_module_navigation(): void
    {
        $groups = Navigation::groups([
            ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/module/dashboard'],
            ['key' => 'documents', 'label' => 'Documents', 'url' => '/module/documents'],
            ['key' => 'settings', 'label' => 'Paramétrage', 'url' => '/module/settings'],
        ]);
        self::assertArrayHasKey('Pilotage', $groups);
        self::assertArrayHasKey('Activité', $groups);
        self::assertArrayHasKey('Configuration', $groups);
    }

    public function test_rejects_duplicate_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Navigation::groups([
            ['key' => 'same', 'label' => 'A', 'url' => '/a'],
            ['key' => 'same', 'label' => 'B', 'url' => '/b'],
        ]);
    }

    public function test_rejects_available_link_without_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Navigation::groups([['key' => 'broken', 'label' => 'Cassé', 'available' => true]]);
    }
}
