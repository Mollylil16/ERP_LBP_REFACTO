<?php

declare(strict_types=1);

namespace App\View\Navigation;

final class EmployeeNavigation
{
    /** @return array<int,array<string,mixed>> */
    public static function items(): array
    {
        return [
            ['group' => 'Accueil', 'key' => 'dashboard', 'label' => 'Mon tableau de bord', 'icon' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>', 'url' => 'espace-employe', 'available' => true],
            ['group' => 'Mes démarches', 'key' => 'requests', 'label' => 'Mes demandes RH', 'icon' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>', 'url' => 'espace-employe/demandes/nouvelle', 'available' => true],
            ['group' => 'Temps & échanges', 'key' => 'attendance', 'label' => 'Mon pointage', 'icon' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>', 'url' => 'espace-employe#pointage', 'available' => true],
            ['group' => 'Temps & échanges', 'key' => 'explanations', 'label' => 'Mes explications', 'icon' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>', 'url' => 'espace-employe#explications', 'available' => true],
            ['group' => 'Mon dossier', 'key' => 'documents', 'label' => 'Mes documents', 'icon' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>', 'url' => 'espace-employe#documents', 'available' => true],
        ];
    }
}
