<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Qui approvisionne une caisse, qui le valide, et qui le voit.
 *
 * Décidé par la direction le 30/09/2026 : la caissière principale saisit
 * l'approvisionnement, le comptable le valide. Deux mains, deux rôles — celui
 * qui remet l'argent n'est pas celui qui l'inscrit aux comptes.
 *
 * L'agence, elle, doit voir ce qu'elle a reçu : depuis qu'un appro validé
 * entre dans l'attendu de son point de caisse, ne pas le lui montrer
 * reviendrait à lui réclamer le soir un argent dont elle ignore l'origine.
 */
final class ApproCaisseAcces
{
    /** @var array<int, string> */
    public const ROLES_LECTURE = [
        'caissiere_principale', 'comptable',
        'dg', 'assistant_dg', 'assistante_dg', 'dg_surveillance',
        'superviseur_general', 'superviseur_regional', 'chef_agence',
        'caissiere', 'gestionnaire_caisse', 'agent_saisie', 'agent_enregistrement',
    ];

    /** La caissière principale, et elle seule : c'est elle qui remet l'argent. */
    public const ROLES_SAISIE = ['caissiere_principale'];

    /** Le comptable, et lui seul : il valide ce qu'il devra imputer. */
    public const ROLES_VALIDATION = ['comptable'];

    public static function peutOuvrir(): bool
    {
        return Auth::isAdmin() || Auth::isAssistantDg() || Auth::hasAnyRole(self::ROLES_LECTURE);
    }

    public static function peutSaisir(): bool
    {
        return Auth::isAdmin() || Auth::hasAnyRole(self::ROLES_SAISIE);
    }

    public static function peutValider(): bool
    {
        return Auth::isAdmin() || Auth::hasAnyRole(self::ROLES_VALIDATION);
    }

    /**
     * Voit-il les appros de toutes les agences ?
     *
     * Un chef d'agence ou une caissière ne voit que la sienne : ce qui entre
     * ailleurs ne le regarde pas.
     */
    public static function voitToutesLesAgences(): bool
    {
        return Auth::isAdmin()
            || Auth::isAssistantDg()
            || Auth::hasAnyRole(['caissiere_principale', 'comptable', 'dg', 'assistant_dg', 'assistante_dg', 'dg_surveillance', 'superviseur_general']);
    }
}
