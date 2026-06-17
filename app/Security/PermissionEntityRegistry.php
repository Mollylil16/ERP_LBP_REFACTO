<?php

namespace App\Security;

final class PermissionEntityRegistry
{
    public const USERS = 'users';
    public const PERMISSION_ENTITIES = 'permission_entities';
    public const USER_PERMISSIONS = 'user_permissions';
    public const RH_EMPLOYEES = 'rh_employees';
    public const RH_EMPLOYEE_HISTORY = 'rh_employee_history';
    public const RH_EMPLOYEE_MUTATIONS = 'rh_employee_mutations';
    public const RH_EXIT_REASONS = 'rh_exit_reasons';
    public const RH_FUNCTIONS = 'rh_functions';
    public const RH_SERVICES = 'rh_services';
    public const RH_STATUSES = 'rh_statuses';
    public const RH_CONTRACTS = 'rh_contracts';
    public const RH_PAYROLL_PARAMS = 'rh_payroll_params';
    public const RH_ATTENDANCE = 'rh_attendance';
    public const RH_PAYROLL = 'rh_payroll';
    public const RH_LEAVES = 'rh_leaves';
    public const COLISAGE_COLIS = 'colisage_colis';
    public const COLISAGE_EXPEDITIONS = 'colisage_expeditions';
    public const CRM_CLIENTS = 'crm_clients';
    public const CRM_OPPORTUNITIES = 'crm_opportunities';

    // Phase 2
    public const FLOTTE_LIVREURS = 'flotte_livreurs';
    public const TRACKING_GPS = 'tracking_gps';
    public const ENTREPOT_INVENTAIRES = 'entrepot_inventaires';

    // Phase 3
    public const TRANSIT_PRESTATAIRES = 'transit_prestataires';
    public const FACTURATION_FACTURES = 'facturation_factures';
    public const FINANCE_RETRAITS = 'finance_retraits';
    public const FINANCE_COMPENSATIONS = 'finance_compensations';
    public const LOGISTIQUE_FOURNITURES = 'logistique_fournitures';

    public static function all(): array
    {
        return [
            self::USERS => [
                'module' => 'Administration',
                'name' => 'Comptes utilisateurs',
                'description' => 'Comptes de connexion, état et rattachement au personnel.',
                'sort_order' => 10,
            ],
            self::PERMISSION_ENTITIES => [
                'module' => 'Administration',
                'name' => 'Entités de permissions',
                'description' => 'Catalogue des données pouvant être protégées.',
                'sort_order' => 20,
            ],
            self::USER_PERMISSIONS => [
                'module' => 'Administration',
                'name' => 'Droits utilisateurs',
                'description' => 'Matrice CRUD attribuée à chaque utilisateur.',
                'sort_order' => 30,
            ],
            self::RH_EMPLOYEES => [
                'module' => 'Ressources humaines',
                'name' => 'Personnel',
                'description' => 'Identité, contact, dossier administratif et situation du personnel.',
                'sort_order' => 100,
            ],
            self::RH_EMPLOYEE_HISTORY => [
                'module' => 'Ressources humaines',
                'name' => 'Historique RH',
                'description' => 'Événements, notes, entrées, sorties et réintégrations.',
                'sort_order' => 110,
            ],
            self::RH_EMPLOYEE_MUTATIONS => [
                'module' => 'Ressources humaines',
                'name' => 'Mutations du personnel',
                'description' => 'Changements de service, fonction, statut et site.',
                'sort_order' => 120,
            ],
            self::RH_EXIT_REASONS => [
                'module' => 'Ressources humaines',
                'name' => 'Motifs de sortie',
                'description' => 'Référentiel des motifs de sortie du personnel.',
                'sort_order' => 130,
            ],
            self::RH_FUNCTIONS => [
                'module' => 'Ressources humaines',
                'name' => 'Fonctions',
                'description' => 'Référentiel des fonctions occupées.',
                'sort_order' => 140,
            ],
            self::RH_SERVICES => [
                'module' => 'Ressources humaines',
                'name' => 'Services',
                'description' => 'Référentiel des services et affectations.',
                'sort_order' => 150,
            ],
            self::RH_STATUSES => [
                'module' => 'Ressources humaines',
                'name' => 'Statuts contractuels',
                'description' => 'Référentiel des statuts du personnel.',
                'sort_order' => 160,
            ],
            self::RH_CONTRACTS => [
                'module' => 'Ressources humaines',
                'name' => 'Contrats & Rémunérations',
                'description' => 'Gestion des contrats de travail, salaires de base et indemnités.',
                'sort_order' => 170,
            ],
            self::RH_PAYROLL_PARAMS => [
                'module' => 'Ressources humaines',
                'name' => 'Paramètres de Paie',
                'description' => 'Référentiel légal et fiscal pour le calcul de la paie.',
                'sort_order' => 180,
            ],
            self::RH_ATTENDANCE => [
                'module' => 'Ressources humaines',
                'name' => 'Pointage & Présences',
                'description' => 'Saisie et importation des présences et heures supplémentaires.',
                'sort_order' => 190,
            ],
            self::RH_PAYROLL => [
                'module' => 'Ressources humaines',
                'name' => 'Gestion de la Paie',
                'description' => 'Calcul des salaires, campagnes de paie et édition des bulletins.',
                'sort_order' => 200,
            ],
            self::RH_LEAVES => [
                'module' => 'Ressources humaines',
                'name' => 'Congés & Absences',
                'description' => 'Gestion des demandes de congés et des soldes associés.',
                'sort_order' => 210,
            ],
            // Colisage & Fret
            self::COLISAGE_COLIS => [
                'module' => 'Colisage & Fret',
                'name' => 'Colis',
                'description' => 'Réception, suivi et retrait des colis.',
                'sort_order' => 300,
            ],
            self::COLISAGE_EXPEDITIONS => [
                'module' => 'Colisage & Fret',
                'name' => 'Expéditions (Manifestes)',
                'description' => 'Groupage de colis, manifestes aérien/maritime/routier.',
                'sort_order' => 310,
            ],
            // CRM
            self::CRM_CLIENTS => [
                'module' => 'CRM',
                'name' => 'Clients & Partenaires',
                'description' => 'Référentiel des expéditeurs, destinataires et prospects.',
                'sort_order' => 350,
            ],
            self::CRM_OPPORTUNITIES => [
                'module' => 'CRM',
                'name' => 'Opportunités commerciales',
                'description' => 'Pipeline de prospection et suivi des opportunités.',
                'sort_order' => 360,
            ],
            // Phase 2
        self::FLOTTE_LIVREURS => [
            'name' => 'Livreurs & Flotte',
            'module' => 'Flotte',
            'description' => 'Gérer les profils de coursiers et de livreurs',
            'sort_order' => 400,
        ],
        self::TRACKING_GPS => [
            'name' => 'Tracking GPS',
            'module' => 'Tracking',
            'description' => 'Mettre à jour et consulter les points de tracking',
            'sort_order' => 410,
        ],
        self::ENTREPOT_INVENTAIRES => [
            'name' => 'Campagnes d\'inventaire',
            'module' => 'Entrepôts',
            'description' => 'Réaliser et valider les inventaires en agence',
            'sort_order' => 420,
        ],
        // Phase 3
        self::TRANSIT_PRESTATAIRES => [
            'name' => 'Prestataires & Douane',
            'module' => 'Transit Douane',
            'description' => 'Gérer les douanes, fret aérien et fournisseurs',
            'sort_order' => 500,
        ],
        self::FACTURATION_FACTURES => [
            'name' => 'Factures Prestataires',
            'module' => 'Facturation',
            'description' => 'Saisie et validation des factures de fret/douane',
            'sort_order' => 510,
        ],
        self::FINANCE_RETRAITS => [
            'name' => 'Retraits Hub (Caisse)',
            'module' => 'Finance',
            'description' => 'Enregistrer les décaissements de la caisse centrale',
            'sort_order' => 520,
        ],
        self::FINANCE_COMPENSATIONS => [
            'name' => 'Compensation Inter-agences',
            'module' => 'Finance',
            'description' => 'Gérer les crédits et dettes entre agences',
            'sort_order' => 530,
        ],
        self::LOGISTIQUE_FOURNITURES => [
            'name' => 'Demandes de fournitures',
            'module' => 'Logistique',
            'description' => 'Gérer les besoins internes des agences (scotch, ramettes...)',
            'sort_order' => 540,
        ],
    ];
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $code): bool
    {
        return isset(self::all()[$code]);
    }

    public static function label(string $code): string
    {
        return self::all()[$code]['name'] ?? $code;
    }

    public static function codesForModule(string $module): array
    {
        return array_keys(array_filter(
            self::all(),
            static fn(array $entity): bool => $entity['module'] === $module
        ));
    }
}
