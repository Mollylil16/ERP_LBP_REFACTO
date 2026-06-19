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
            ['group' => 'Pilotage', 'key' => 'configuration', 'label' => 'Design & contenus', 'icon' => 'UI', 'url' => 'site-admin/configuration', 'available' => true],
            ['group' => 'Pilotage', 'key' => 'analytics', 'label' => 'Audience & clics', 'icon' => 'AN', 'url' => 'site-admin/analytics', 'available' => true],
            ['group' => 'Relation client', 'key' => 'messages', 'label' => 'Messages clients', 'icon' => 'MSG', 'url' => 'site-admin/messages', 'available' => true],
            ['group' => 'Site public', 'key' => 'website', 'label' => 'Voir le site', 'icon' => 'WEB', 'url' => 'site', 'available' => true],
        ];
    }
}
