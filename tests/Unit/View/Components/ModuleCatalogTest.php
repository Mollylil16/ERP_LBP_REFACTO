<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\ModuleCatalog;
use PHPUnit\Framework\TestCase;

final class ModuleCatalogTest extends TestCase
{
    public function testComponentsRenderIndependently(): void
    {
        $module = [
            'key' => 'rh',
            'label' => 'RH',
            'code' => 'RH',
            'icon' => 'rh',
            'description' => 'Gestion des collaborateurs',
            'url' => '/rh/dashboard',
            'class' => 'module-rh',
            'status' => 'Disponible',
            'is_maintenance' => false,
        ];

        $hero = ModuleCatalog::hero('Amani', 1);
        $filter = ModuleCatalog::moduleFilter([['value' => 'rh', 'label' => 'RH · RH']], 1);
        $grid = ModuleCatalog::moduleGrid([$module]);
        $card = ModuleCatalog::moduleCard($module);
        $note = ModuleCatalog::footerNote('Catalogue', 'Choisissez un module.', 'Retour', 'dashboard');

        self::assertStringContainsString('Bonjour Amani', $hero);
        self::assertStringContainsString('name="portal_modules[]"', $filter);
        self::assertStringContainsString('data-portal-module-filter="1"', $filter);
        self::assertStringContainsString('data-module-key="rh"', $grid);
        self::assertStringContainsString('Ouvrir le module RH', $card);
        self::assertStringContainsString('Choisissez un module.', $note);
    }
}
