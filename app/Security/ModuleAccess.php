<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Qui a le droit d'ouvrir quel module métier.
 *
 * Un seul endroit décide, pour deux usages qui doivent toujours dire la même
 * chose : la tuile affichée sur le portail, et le contrôle à l'entrée du
 * contrôleur. Tant que les deux listes vivaient séparément, un agent voyait des
 * tuiles qui le rejetaient au clic.
 *
 * Les rôles retenus viennent du métier, pas de la hiérarchie : on ouvre un
 * module à ceux qui ont besoin de ses données pour travailler, et la lecture est
 * toujours plus large que l'écriture.
 */
final class ModuleAccess
{
    /**
     * Rôles portés par des comptes réels mais absents du catalogue
     * AdminService::AVAILABLE_ROLES.
     *
     * La base de production en compte onze, dont « agent_saisie » qui est le
     * rôle le plus répandu de l'entreprise. Les ignorer aurait fermé les modules
     * aux personnes qui en ont le plus besoin : un passeur en douane sans accès
     * à Transit Douane, un agent de call center sans accès au suivi des colis.
     *
     * Ils sont donc nommés ici tels qu'ils existent. Le catalogue reste à
     * compléter côté Administration, mais c'est une décision de paramétrage qui
     * n'a pas à bloquer les habilitations.
     *
     * @var array<int, string>
     */
    public const ROLES_HORS_CATALOGUE = [
        'agent_saisie',
        'agent_call_center',
        'agent_exploitation',
        'agent_export',
        'passeur_douane',
        'declarant_douane',
        'gestionnaire_caisse',
        'dg_surveillance',
        'responsable_rh',
        'responsable_marketing',
        'responsable_logistique',
    ];

    /**
     * Rôles autorisés à consulter chaque module, par slug.
     *
     * 'admin' et 'dg' figurent partout : RoleMiddleware les laisse déjà passer,
     * les nommer ici garde la liste lisible sans dépendre de cet implicite.
     *
     * @var array<string, array<int, string>>
     */
    private const LECTURE = [
        // Occupation des rayons, colis en souffrance, gardiennage : le magasin,
        // ceux qui y déposent des colis et ceux qui en répondent.
        'entrepots' => [
            'admin', 'dg', 'assistant_dg', 'dg_surveillance',
            'superviseur_general', 'superviseur_regional', 'chef_agence',
            'responsable_logistique',
            'agent_groupage', 'agent_enregistrement', 'agent_saisie',
            'agent_exploitation', 'agent',
        ],

        // Livreurs, véhicules, disponibilité : l'encadrement terrain et
        // l'exploitation. Un agent de saisie n'a pas à suivre les livreurs.
        'flotte-transport' => [
            'admin', 'dg', 'assistant_dg', 'dg_surveillance',
            'superviseur_general', 'superviseur_regional', 'chef_agence',
            'responsable_logistique', 'agent_groupage', 'agent_exploitation',
        ],

        // Lots à dédouaner et coût au kilo : ceux qui font la douane, et ceux
        // qui en supportent le coût.
        'transit-douane' => [
            'admin', 'dg', 'assistant_dg', 'dg_surveillance',
            'superviseur_general', 'chef_agence', 'comptable',
            'agent_transit', 'transit', 'transitaire',
            'passeur_douane', 'declarant_douane', 'agent_export',
        ],

        // Valeur et risque par client : commerce, recouvrement, caisse.
        // Volontairement fermé aux agents de saisie : c'est une vue de marge.
        'portefeuille-clients' => [
            'admin', 'dg', 'assistant_dg', 'dg_surveillance',
            'superviseur_general', 'superviseur_regional', 'chef_agence',
            'comptable', 'caissiere_principale', 'gestionnaire_caisse',
            'suivi_recouvrement', 'responsable_marketing',
        ],

        // Annuaire des correspondants : consultable par ceux qui traitent avec
        // l'étranger.
        'agents-correspondants' => [
            'admin', 'dg', 'assistant_dg', 'dg_surveillance',
            'superviseur_general', 'superviseur_regional', 'chef_agence',
            'agent_transit', 'transit', 'transitaire', 'comptable',
            'passeur_douane', 'declarant_douane', 'agent_export',
            'responsable_marketing',
        ],

        // Suivi d'un colis : le module le plus ouvert, parce que n'importe qui
        // au contact d'un client doit pouvoir répondre « où est mon colis ».
        'tracking-colis' => [
            'admin', 'dg', 'assistant_dg', 'dg_surveillance',
            'superviseur_general', 'superviseur_regional', 'chef_agence',
            'caissiere_principale', 'caissiere', 'gestionnaire_caisse', 'comptable',
            'agent_enregistrement', 'agent_groupage', 'agent_saisie',
            'agent_exploitation', 'agent_export', 'agent', 'agent_call_center',
            'agent_transit', 'transit', 'transitaire',
            'passeur_douane', 'declarant_douane',
            'suivi_recouvrement', 'responsable_logistique',
        ],
    ];

    /**
     * Rôles autorisés à modifier. Seul l'annuaire des correspondants écrit
     * aujourd'hui : les cinq autres modules sont des vues de lecture sur des
     * données saisies ailleurs, et doivent le rester pour éviter deux points de
     * saisie pour la même information.
     *
     * @var array<string, array<int, string>>
     */
    private const GESTION = [
        'agents-correspondants' => [
            'admin', 'dg', 'assistant_dg', 'superviseur_general', 'chef_agence',
        ],
    ];

    /**
     * Rôles voyant l'ensemble du réseau, y compris hors catalogue.
     *
     * @var array<int, string>
     */
    private const PORTEE_RESEAU = [
        'dg', 'assistant_dg', 'assistante_dg', 'dg_surveillance',
        'superviseur_general', 'comptable',
    ];

    /**
     * @return array<int, string>
     */
    public static function rolesLecture(string $slug): array
    {
        return self::LECTURE[$slug] ?? ['admin', 'dg'];
    }

    /**
     * @return array<int, string>
     */
    public static function rolesGestion(string $slug): array
    {
        return self::GESTION[$slug] ?? ['admin', 'dg'];
    }

    public static function estGere(string $slug): bool
    {
        return isset(self::LECTURE[$slug]);
    }

    /** L'utilisateur connecté peut-il ouvrir ce module ? */
    public static function peutConsulter(string $slug): bool
    {
        if (!self::estGere($slug)) {
            return true; // Module hors périmètre : ses propres règles s'appliquent.
        }

        return Auth::isAdmin()
            || Auth::isAssistantDg()
            || Auth::hasAnyRole(self::rolesLecture($slug));
    }

    /** L'utilisateur connecté peut-il modifier dans ce module ? */
    public static function peutGerer(string $slug): bool
    {
        return Auth::isAdmin()
            || Auth::isAssistantDg()
            || Auth::hasAnyRole(self::rolesGestion($slug));
    }

    /**
     * Les rôles hors de PORTEE_RESEAU sont ramenés à leur agence de
     * rattachement, comme partout ailleurs dans l'ERP.
     */
    public static function porteeReseau(): bool
    {
        return Auth::isAdmin()
            || Auth::isAssistantDg()
            || Auth::hasAnyRole(self::PORTEE_RESEAU);
    }

    /**
     * Agence sur laquelle borner les requêtes, ou null pour tout le réseau.
     *
     * Renvoie 0 pour un utilisateur local sans agence de rattachement : aucune
     * ligne ne porte l'agence 0, la vue est donc vide. Laisser passer null
     * aurait ouvert tout le réseau à un compte mal configuré.
     */
    public static function agenceVisible(): ?int
    {
        if (self::porteeReseau()) {
            return null;
        }

        return Auth::agenceId() ?: 0;
    }
}
