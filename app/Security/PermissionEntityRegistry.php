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
    
    public const EXPLOITATION_SYNTHESE = 'exploitation_synthese';
    public const EXPLOITATION_TRACKING = 'exploitation_tracking';
    public const EXPLOITATION_CREDITS = 'exploitation_credits';
    public const EXPLOITATION_FOURNITURES = 'exploitation_fournitures';

    public const CALL_CENTER_VIEW = 'call_center_view';
    public const CALL_CENTER_MANAGE = 'call_center_manage';
    public const RAPPORTS_AGENCE = 'rapports_agence';
    public const EXPORTER_RAPPORTS_EXCEL = 'exporter_rapports_excel';

    public const SAISIR_FACTURE = 'saisir_facture';
    public const MODIFIER_FACTURE_APRES_CREATION = 'modifier_facture_apres_creation';
    public const CONSULTER_TOUTES_FACTURES_TOUTES_AGENCES = 'consulter_toutes_factures_toutes_agences';
    public const EXPORTER_COLISAGE_SANS_MONTANT = 'exporter_colisage_sans_montant';
    public const EXPORTER_FACTURATION_AVEC_MONTANT = 'exporter_facturation_avec_montant';

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
            self::EXPLOITATION_SYNTHESE => [
                'module' => 'Facturation',
                'name' => 'Synthèse d\'exploitation',
                'description' => 'Accès aux indicateurs et recettes consolidés.',
                'sort_order' => 200,
            ],
            self::EXPLOITATION_TRACKING => [
                'module' => 'Facturation',
                'name' => 'Suivi logistique GPS',
                'description' => 'Mise à jour des coordonnées des expéditions.',
                'sort_order' => 210,
            ],
            self::EXPLOITATION_CREDITS => [
                'module' => 'Facturation',
                'name' => 'Compensation inter-agences',
                'description' => 'Gestion et règlement des créances réciproques.',
                'sort_order' => 220,
            ],
            self::EXPLOITATION_FOURNITURES => [
                'module' => 'Facturation',
                'name' => 'Fournitures de bureau',
                'description' => 'Validation et circuit des fournitures de bureau.',
                'sort_order' => 230,
            ],
            self::RAPPORTS_AGENCE => [
                'module' => 'Facturation',
                'name' => 'Rapports journaliers par agence',
                'description' => 'Accéder aux rapports journaliers et mensuels par agence.',
                'sort_order' => 235,
            ],
            self::EXPORTER_RAPPORTS_EXCEL => [
                'module' => 'Facturation',
                'name' => 'Exporter rapports agence (Excel/CSV)',
                'description' => 'Exporter les rapports journaliers et mensuels par agence au format Excel ou CSV.',
                'sort_order' => 236,
            ],
            self::CALL_CENTER_VIEW => [
                'module' => 'Call Center',
                'name' => 'Call Center - Consulter',
                'description' => 'Consulter le tableau de bord, les appels, les litiges et la vue des rayons en temps réel.',
                'sort_order' => 240,
            ],
            self::CALL_CENTER_MANAGE => [
                'module' => 'Call Center',
                'name' => 'Call Center - Gérer',
                'description' => 'Enregistrer des appels, ouvrir et résoudre des litiges.',
                'sort_order' => 250,
            ],
            self::SAISIR_FACTURE => [
                'module' => 'Facturation',
                'name' => 'Saisir / Créer une facture',
                'description' => 'Permet à l\'agent de créer une nouvelle facture client.',
                'sort_order' => 300,
            ],
            self::MODIFIER_FACTURE_APRES_CREATION => [
                'module' => 'Facturation',
                'name' => 'Modifier une facture après création',
                'description' => 'Privilège responsable pour modifier ou annuler une facture verrouillée.',
                'sort_order' => 310,
            ],
            self::CONSULTER_TOUTES_FACTURES_TOUTES_AGENCES => [
                'module' => 'Facturation',
                'name' => 'Consulter toutes les factures (Toutes agences)',
                'description' => 'Privilège responsable pour accéder aux factures de l\'ensemble des agences.',
                'sort_order' => 320,
            ],
            self::EXPORTER_COLISAGE_SANS_MONTANT => [
                'module' => 'Logistique',
                'name' => 'Exporter le suivi colisage (sans montant)',
                'description' => 'Exporter les données de suivi colisage en PDF et Excel sans afficher de montant.',
                'sort_order' => 330,
            ],
            self::EXPORTER_FACTURATION_AVEC_MONTANT => [
                'module' => 'Facturation',
                'name' => 'Exporter la facturation (avec montant)',
                'description' => 'Exporter la facturation et les états financiers filtrés avec montants.',
                'sort_order' => 340,
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
