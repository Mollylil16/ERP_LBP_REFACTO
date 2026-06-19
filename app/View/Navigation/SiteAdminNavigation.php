<?php

declare(strict_types=1);

namespace App\View\Navigation;

final class SiteAdminNavigation
{
    /** @return array<int,array<string,mixed>> */
    public static function items(): array
    {
        return [
            ['group' => 'Pilotage', 'key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => 'site-admin/dashboard', 'available' => true],
            ['group' => 'Site public', 'key' => 'website', 'label' => 'Voir le site', 'icon' => 'WEB', 'url' => 'site', 'available' => true],
        ];
    }
}
