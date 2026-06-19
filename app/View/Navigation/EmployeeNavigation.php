<?php

declare(strict_types=1);

namespace App\View\Navigation;

final class EmployeeNavigation
{
    /** @return array<int,array<string,mixed>> */
    public static function items(): array
    {
        return [
            ['group' => 'Accueil', 'key' => 'dashboard', 'label' => 'Mon tableau de bord', 'icon' => 'DB', 'url' => 'espace-employe', 'available' => true],
            ['group' => 'Mes démarches', 'key' => 'requests', 'label' => 'Mes demandes RH', 'icon' => 'DR', 'url' => 'espace-employe/demandes/nouvelle', 'available' => true],
            ['group' => 'Temps & échanges', 'key' => 'attendance', 'label' => 'Mon pointage', 'icon' => 'PT', 'url' => 'espace-employe#pointage', 'available' => true],
            ['group' => 'Temps & échanges', 'key' => 'explanations', 'label' => 'Mes explications', 'icon' => 'EX', 'url' => 'espace-employe#explications', 'available' => true],
            ['group' => 'Mon dossier', 'key' => 'documents', 'label' => 'Mes documents', 'icon' => 'DO', 'url' => 'espace-employe#documents', 'available' => true],
        ];
    }
}
