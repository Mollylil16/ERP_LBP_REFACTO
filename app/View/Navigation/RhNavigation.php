<?php

declare(strict_types=1);

namespace App\View\Navigation;

final class RhNavigation
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
                'url' => 'rh/dashboard',
                'available' => true
            ],
            [
                'group' => 'Collaborateurs',
                'key' => 'personnel',
                'label' => 'Dossiers du personnel',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                'url' => 'rh/personnel',
                'available' => true
            ],
            [
                'group' => 'Collaborateurs',
                'key' => 'organization',
                'label' => 'Organisation & carrière',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22v-5"></path><path d="M5 17H2v-6h6v6H5zM14 17h-4v-6h6v6h-2zM22 17h-4v-6h6v6h-2zM12 11V6"></path><path d="M9 6h6V2H9v4z"></path></svg>',
                'url' => 'rh/cycle-vie?section=organization',
                'available' => true
            ],
            [
                'group' => 'Collaborateurs',
                'key' => 'mutations',
                'label' => 'Promotions & mutations',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 4 4-4 4"></path><path d="M20 7H9a4 4 0 0 0-4 4v3"></path><path d="m8 21-4-4 4-4"></path><path d="M4 17h11a4 4 0 0 0 4-4v-3"></path></svg>',
                'url' => 'rh/mutations',
                'available' => true
            ],
            [
                'group' => 'Parcours & talents',
                'key' => 'contracts',
                'label' => 'Contrats & essais',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>',
                'url' => 'rh/cycle-vie?section=contracts',
                'available' => true
            ],
            [
                'group' => 'Parcours & talents',
                'key' => 'assignments',
                'label' => 'Missions & affectations',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>',
                'url' => 'rh/cycle-vie?section=assignments',
                'available' => true
            ],
            [
                'group' => 'Parcours & talents',
                'key' => 'evaluations',
                'label' => 'Performances',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>',
                'url' => 'rh/cycle-vie?section=evaluations',
                'available' => true
            ],
            [
                'group' => 'Parcours & talents',
                'key' => 'trainings',
                'label' => 'Formation',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c0 2 2.5 3 6 3s6-1 6-3v-5"></path></svg>',
                'url' => 'rh/cycle-vie?section=trainings',
                'available' => true
            ],
            [
                'group' => 'Entrées & sorties',
                'key' => 'recruitment',
                'label' => 'Recrutement & onboarding',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>',
                'url' => 'rh/cycle-vie?section=recruitment',
                'available' => true
            ],
            [
                'group' => 'Entrées & sorties',
                'key' => 'sorties',
                'label' => 'Départs & offboarding',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
                'url' => 'rh/mouvements',
                'available' => true
            ],
            [
                'group' => 'Administration RH',
                'key' => 'attendance',
                'label' => 'Temps & présence',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
                'url' => 'rh/pointage',
                'available' => true
            ],
            [
                'group' => 'Administration RH',
                'key' => 'payroll',
                'label' => 'Paie & variables',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>',
                'url' => 'rh/paie',
                'available' => true
            ],
            [
                'group' => 'Administration RH',
                'key' => 'discipline',
                'label' => 'Discipline',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
                'url' => 'rh/cycle-vie?section=discipline',
                'available' => true
            ],
            [
                'group' => 'Administration RH',
                'key' => 'workflows',
                'label' => 'Validations RH',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
                'url' => 'rh/cycle-vie?section=workflows',
                'available' => true
            ],
            [
                'group' => 'Administration RH',
                'key' => 'planning-conges',
                'label' => 'Planning congés',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
                'url' => 'rh/planning-conges',
                'available' => true
            ],
            [
                'group' => 'Configuration',
                'key' => 'settings',
                'label' => 'Paramétrage',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
                'url' => 'rh/parametrage',
                'available' => true
            ],
        ];
    }
}
