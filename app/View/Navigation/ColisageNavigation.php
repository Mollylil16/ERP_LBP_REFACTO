<?php

declare(strict_types=1);

namespace App\View\Navigation;

final class ColisageNavigation
{
    /** @return array<int,array<string,mixed>> */
    public static function items(): array
    {
        return [
            [
                'group' => 'Pilotage',
                'key' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect></svg>',
                'url' => 'colisage/dashboard',
                'available' => true
            ],
            [
                'group' => 'Activité',
                'key' => 'operations',
                'label' => 'Opérations (Colis)',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
                'url' => 'colisage/parcels',
                'available' => true
            ],
            [
                'group' => 'Activité',
                'key' => 'groupage',
                'label' => 'Groupage / Fret',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>',
                'url' => 'colisage/groupage',
                'available' => true
            ],
            [
                'group' => 'Activité',
                'key' => 'autres',
                'label' => 'Autres envois',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"></path></svg>',
                'url' => 'colisage/autres',
                'available' => true
            ],
            [
                'group' => 'Activité',
                'key' => 'documents',
                'label' => 'Documents',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>',
                'url' => 'colisage/documents',
                'available' => true
            ],
            [
                'group' => 'Exploitation',
                'key' => 'exploitation_synthese',
                'label' => 'Synthèse globale',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/></svg>',
                'url' => 'colisage/exploitation/synthese',
                'available' => \App\Helpers\Auth::can(\App\Security\PermissionEntityRegistry::EXPLOITATION_SYNTHESE)
            ],
            [
                'group' => 'Exploitation',
                'key' => 'exploitation_tracking',
                'label' => 'Tracking GPS',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 21.7c3.6-4 6.3-7.7 6.3-11.7C18.3 5.4 14.6 2 12 2S5.7 5.4 5.7 10c0 4 2.7 7.7 6.3 11.7z"/></svg>',
                'url' => 'colisage/exploitation/tracking',
                'available' => \App\Helpers\Auth::can(\App\Security\PermissionEntityRegistry::EXPLOITATION_TRACKING)
            ],
            [
                'group' => 'Exploitation',
                'key' => 'exploitation_credits',
                'label' => 'Compensation',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="12" y1="4" x2="12" y2="20"/><line x1="2" y1="12" x2="22" y2="12"/></svg>',
                'url' => 'colisage/exploitation/credits',
                'available' => \App\Helpers\Auth::can(\App\Security\PermissionEntityRegistry::EXPLOITATION_CREDITS)
            ],
            [
                'group' => 'Analyse',
                'key' => 'reporting',
                'label' => 'Reporting',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
                'url' => 'colisage/reporting',
                'available' => true
            ],
            [
                'group' => 'Configuration',
                'key' => 'settings',
                'label' => 'Paramétrage',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
                'url' => 'colisage/settings',
                'available' => true
            ],
        ];
    }
}
