<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Middleware\AuthMiddleware;
use App\Security\PermissionAction;
use App\Security\PermissionEntityRegistry;

/**
 * Portail central de sélection des modules ERP.
 *
 * Point d'entrée privé après connexion : connexion -> selection_portail -> module métier.
 */
class SelectionPortailController extends BaseController
{
    public function index(): void
    {
        AuthMiddleware::check();

        $modules = [
            [
                'key' => 'finance',
                'label' => 'Finance',
                'code' => 'FIN',
                'icon' => 'finance',
                'description' => 'Facturation, règlements, décaissements, caisse, états financiers et suivi des coûts par dossier de transit.',
                'url' => '/finance/dashboard',
                'class' => 'module-finance',
                'status' => 'Socle initial',
                'keywords' => 'finance facture facturation règlement paiement caisse décaissement budget trésorerie transit',
            ],
            [
                'key' => 'rh',
                'label' => 'RH',
                'code' => 'RH',
                'icon' => 'rh',
                'description' => 'Employés, présences, contrats, demandes administratives, rôles opérationnels et habilitations internes.',
                'url' => '/rh/dashboard',
                'class' => 'module-rh',
                'status' => 'Socle initial',
                'keywords' => 'rh ressources humaines employés présence contrats congés demandes personnel habilitations',
            ],
            [
                'key' => 'colisage',
                'label' => 'Colisage',
                'code' => 'COL',
                'icon' => 'colisage',
                'description' => 'Liste de colisage, marchandises, volumes, poids, conteneurs, références BL et contrôle documentaire.',
                'url' => '/colisage/dashboard',
                'class' => 'module-colisage',
                'status' => 'Prioritaire',
                'keywords' => 'colisage packing list colis marchandises volume poids conteneur bl connaissement douane',
            ],
            [
                'key' => 'logistique',
                'label' => 'Logistique',
                'code' => 'LOG',
                'icon' => 'logistique',
                'description' => 'Enlèvements, livraisons, transporteurs, véhicules, mouvements marchandises et suivi terrain.',
                'url' => '/logistique/dashboard',
                'class' => 'module-logistique',
                'status' => 'Socle initial',
                'keywords' => 'logistique transport livraison enlèvement camion véhicule chauffeur mouvement marchandise tracking',
            ],

            [
                'key' => 'employee',
                'label' => 'Espace employé',
                'code' => 'EMP',
                'icon' => 'employee',
                'description' => 'Demandes personnelles, pointage, documents, notifications, formations et suivi administratif collaborateur.',
                'url' => '/espace-employe/dashboard',
                'class' => 'module-employee',
                'status' => 'Portail personnel',
                'keywords' => 'employé espace personnel demandes pointage documents formation notification dossier administratif',
            ],
            
            [
                'key' => 'crm',
                'label' => 'CRM',
                'code' => 'CRM',
                'icon' => 'crm',
                'description' => 'Clients, prospects, relances, opportunités, historique commercial et suivi relationnel transit.',
                'url' => '/crm/dashboard',
                'class' => 'module-crm',
                'status' => 'Nouveau module',
                'keywords' => 'crm client prospect relance opportunité commercial import export devis suivi',
            ],
            [
                'key' => 'tickets',
                'label' => 'Tickets',
                'code' => 'TIC',
                'icon' => 'tickets',
                'description' => 'Demandes interpersonnelles et interservices, SLA, affectations, relances et traçabilité interne.',
                'url' => '/tickets/dashboard',
                'class' => 'module-tickets',
                'status' => 'Nouveau module',
                'keywords' => 'ticket demande service collègue interaction suivi relance sla support interne',
            ],
            [
                'key' => 'website',
                'label' => 'Site internet',
                'code' => 'WEB',
                'icon' => 'website',
                'description' => 'Pilotage du site public : publicité, suivi colis, demandes de devis, services et agences.',
                'url' => '/site-admin/dashboard',
                'class' => 'module-website',
                'status' => 'Public + backoffice',
                'keywords' => 'site internet vitrine tracking colis publicité devis import export web',
            ],
            [
                'key' => 'admin',
                'label' => 'Admin',
                'code' => 'ADM',
                'icon' => 'admin',
                'description' => 'Utilisateurs, droits, paramètres société, sécurité, référentiels, journaux d’audit et configuration globale.',
                'url' => '/admin/dashboard',
                'class' => 'module-admin',
                'status' => 'Noyau système',
                'keywords' => 'admin administration utilisateurs droits rôles permissions paramètres sécurité audit configuration',
            ],
        ];

        $modules = array_values(array_filter($modules, static function (array $module): bool {
            if ($module['key'] === 'admin') {
                return Auth::user()?->isAdmin ?? false;
            }
            if ($module['key'] === 'rh') {
                $requirements = array_fill_keys(
                    PermissionEntityRegistry::codesForModule('Ressources humaines'),
                    PermissionAction::VIEW
                );
                return Auth::canAny($requirements);
            }
            return true;
        }));

        foreach ($modules as &$module) {
            if ($module['key'] === 'rh' && !Auth::can(PermissionEntityRegistry::RH_EMPLOYEES)) {
                $module['url'] = '/rh/personnel';
            }
        }
        unset($module);

        $user = [
            'id' => Auth::id(),
            'name' => Auth::user()?->fullName ?? 'Administrateur',
        ];

        $this->view('selection_portail/index', [
            'pageTitle' => 'Sélection portail',
            'user' => $user,
            'modules' => $modules,
        ]);
    }
}
