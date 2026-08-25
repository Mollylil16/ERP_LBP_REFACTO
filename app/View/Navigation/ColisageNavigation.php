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
                'group' => 'Opération',
                'key' => 'operations',
                'label' => 'Vue d\'ensemble (tous les colis)',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
                'url' => 'colisage/parcels',
                'available' => true
            ],

            // Groupage Cargo (Aérien) — sous-menu du menu déroulant Opération
            [
                'group' => 'Opération',
                'subgroup' => 'Groupage Cargo',
                'key' => 'op_lb_ci',
                'label' => 'LB-CI : Abidjan ➔ France',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-1.1.1-1.4.6l-.6.9c-.3.4-.2 1 .2 1.3L8 13l-3 3-2-1c-.4-.2-.9-.1-1.2.2l-.6.6c-.3.3-.3.8 0 1.1l2.5 2.5c.3.3.8.3 1.1 0l.6-.6c.3-.3.4-.8.2-1.2l-1-2 3-3 3.5 4.5c.3.4.9.5 1.3.2l.9-.6c.5-.3.7-.9.6-1.4z"></path></svg>',
                'url' => 'operation/LB-CI/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Groupage Cargo',
                'key' => 'op_lb_fr',
                'label' => 'LB-FR : France ➔ Abidjan',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-1.1.1-1.4.6l-.6.9c-.3.4-.2 1 .2 1.3L8 13l-3 3-2-1c-.4-.2-.9-.1-1.2.2l-.6.6c-.3.3-.3.8 0 1.1l2.5 2.5c.3.3.8.3 1.1 0l.6-.6c.3-.3.4-.8.2-1.2l-1-2 3-3 3.5 4.5c.3.4.9.5 1.3.2l.9-.6c.5-.3.7-.9.6-1.4z"></path></svg>',
                'url' => 'operation/LB-FR/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Groupage Cargo',
                'key' => 'op_s_fr',
                'label' => 'S-FR : Sénégal ➔ France',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-1.1.1-1.4.6l-.6.9c-.3.4-.2 1 .2 1.3L8 13l-3 3-2-1c-.4-.2-.9-.1-1.2.2l-.6.6c-.3.3-.3.8 0 1.1l2.5 2.5c.3.3.8.3 1.1 0l.6-.6c.3-.3.4-.8.2-1.2l-1-2 3-3 3.5 4.5c.3.4.9.5 1.3.2l.9-.6c.5-.3.7-.9.6-1.4z"></path></svg>',
                'url' => 'operation/S-FR/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Groupage Cargo',
                'key' => 'op_s_ci',
                'label' => 'S-CI : Sénégal ➔ Côte d\'Ivoire',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-1.1.1-1.4.6l-.6.9c-.3.4-.2 1 .2 1.3L8 13l-3 3-2-1c-.4-.2-.9-.1-1.2.2l-.6.6c-.3.3-.3.8 0 1.1l2.5 2.5c.3.3.8.3 1.1 0l.6-.6c.3-.3.4-.8.2-1.2l-1-2 3-3 3.5 4.5c.3.4.9.5 1.3.2l.9-.6c.5-.3.7-.9.6-1.4z"></path></svg>',
                'url' => 'operation/S-CI/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Groupage Cargo',
                'key' => 'op_lb_ca',
                'label' => 'LB-CA : Abidjan ➔ Canada',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-1.1.1-1.4.6l-.6.9c-.3.4-.2 1 .2 1.3L8 13l-3 3-2-1c-.4-.2-.9-.1-1.2.2l-.6.6c-.3.3-.3.8 0 1.1l2.5 2.5c.3.3.8.3 1.1 0l.6-.6c.3-.3.4-.8.2-1.2l-1-2 3-3 3.5 4.5c.3.4.9.5 1.3.2l.9-.6c.5-.3.7-.9.6-1.4z"></path></svg>',
                'url' => 'operation/LB-CA/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Groupage Cargo',
                'key' => 'op_f_sn',
                'label' => 'F-SN : France ➔ Sénégal',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-1.1.1-1.4.6l-.6.9c-.3.4-.2 1 .2 1.3L8 13l-3 3-2-1c-.4-.2-.9-.1-1.2.2l-.6.6c-.3.3-.3.8 0 1.1l2.5 2.5c.3.3.8.3 1.1 0l.6-.6c.3-.3.4-.8.2-1.2l-1-2 3-3 3.5 4.5c.3.4.9.5 1.3.2l.9-.6c.5-.3.7-.9.6-1.4z"></path></svg>',
                'url' => 'operation/F-SN/saisir',
                'available' => true
            ],

            // Colis Rapide (Aérien/Voyageur) — sous-menu du menu déroulant Opération
            [
                'group' => 'Opération',
                'subgroup' => 'Colis Rapide',
                'key' => 'op_ca_ci',
                'label' => 'CA-CI : Abidjan ➔ Paris',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
                'url' => 'operation/CA-CI/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Colis Rapide',
                'key' => 'op_ca_fr',
                'label' => 'CA-FR : Paris ➔ Abidjan',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
                'url' => 'operation/CA-FR/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Colis Rapide',
                'key' => 'op_ca_sn',
                'label' => 'CA-SN : Sénégal ➔ CI',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
                'url' => 'operation/CA-SN/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Colis Rapide',
                'key' => 'op_ca_is',
                'label' => 'CA-IS : CI ➔ Sénégal',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
                'url' => 'operation/CA-IS/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Colis Rapide',
                'key' => 'op_ca_ic',
                'label' => 'CA-IC : CI ➔ Canada',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
                'url' => 'operation/CA-IC/saisir',
                'available' => true
            ],
            [
                'group' => 'Opération',
                'subgroup' => 'Colis Rapide',
                'key' => 'op_ca_cc',
                'label' => 'CA-CC : Canada ➔ Abidjan',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
                'url' => 'operation/CA-CC/saisir',
                'available' => true
            ],

            // DHL — sous-menu du menu déroulant Opération
            [
                'group' => 'Opération',
                'subgroup' => 'DHL',
                'key' => 'op_dhl',
                'label' => 'DHL Express International',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
                'url' => 'operation/DHL/saisir',
                'available' => true
            ],

            [
                'group' => 'Activité',
                'key' => 'filtre_recherche',
                'label' => 'Filtre & Recherche',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                'url' => 'facturation/filtre',
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
                'group' => 'Analyse',
                'key' => 'rapports_agence',
                'label' => 'Rapports Journaliers',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
                'url' => 'colisage/rapports',
                'available' => \App\Helpers\Auth::can(\App\Security\PermissionEntityRegistry::RAPPORTS_AGENCE) || \App\Helpers\Auth::can(\App\Security\PermissionEntityRegistry::EXPLOITATION_SYNTHESE)
            ],
            [
                'group' => 'Configuration',
                'key' => 'settings',
                'label' => 'Paramétrage',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
                'url' => 'colisage/settings',
                'available' => true
            ],
            [
                'group' => 'Aide',
                'key' => 'guide_utilisation',
                'label' => 'Guide d\'Utilisation',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
                'url' => 'colisage/guide',
                'available' => true
            ],
        ];
    }
}
