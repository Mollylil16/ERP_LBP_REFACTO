<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Middleware\AuthMiddleware;

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
                'url' => '/dashboard',
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
                'url' => '/dashboard',
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
                'url' => '/dashboard',
                'class' => 'module-logistique',
                'status' => 'Socle initial',
                'keywords' => 'logistique transport livraison enlèvement camion véhicule chauffeur mouvement marchandise tracking',
            ],
            [
                'key' => 'admin',
                'label' => 'Admin',
                'code' => 'ADM',
                'icon' => 'admin',
                'description' => 'Utilisateurs, droits, paramètres société, sécurité, référentiels, journaux d’audit et configuration globale.',
                'url' => '/dashboard',
                'class' => 'module-admin',
                'status' => 'Noyau système',
                'keywords' => 'admin administration utilisateurs droits rôles permissions paramètres sécurité audit configuration',
            ],
        ];

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
