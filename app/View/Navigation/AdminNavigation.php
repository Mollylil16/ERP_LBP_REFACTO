<?php

declare(strict_types=1);

namespace App\View\Navigation;

final class AdminNavigation
{
    /** @return array<int,array<string,mixed>> */
    public static function items(): array
    {
        return [
            ['group' => 'Pilotage', 'key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => 'admin/dashboard', 'available' => true],
            ['group' => 'Accès & sécurité', 'key' => 'users', 'label' => 'Utilisateurs', 'icon' => 'UT', 'url' => 'admin/users', 'available' => true],
            ['group' => 'Accès & sécurité', 'key' => 'permissions', 'label' => 'Permissions', 'icon' => 'DR', 'url' => 'admin/permissions', 'available' => true],
            ['group' => 'Accès & sécurité', 'key' => 'roles', 'label' => 'Rôles et anomalies', 'icon' => 'RL', 'url' => 'admin/roles', 'available' => true],
            ['group' => 'Accès & sécurité', 'key' => 'journal', 'label' => 'Journal des comptes', 'icon' => 'JRN', 'url' => 'admin/journal', 'available' => true],
            ['group' => 'Qualité', 'key' => 'tests', 'label' => 'Santé & tests', 'icon' => 'TST', 'url' => 'admin/system-tests', 'available' => true],
        ];
    }
}
