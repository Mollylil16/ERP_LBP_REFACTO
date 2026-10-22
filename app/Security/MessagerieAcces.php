<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Qui peut ouvrir une discussion de la messagerie dont il n'est pas membre.
 *
 * Le DG et l'administrateur seulement (cahier des charges validé en octobre
 * 2026). L'assistante DG n'y figure pas : lire les échanges privés du
 * personnel est un pouvoir de direction, pas de secrétariat. Chaque
 * ouverture de ce genre est inscrite par le service dans msg_acces_direction
 * et dans le journal d'audit.
 *
 * Tout utilisateur actif, lui, utilise la messagerie : aucune autre règle
 * de rôle n'existe ici, la porte est l'authentification.
 */
final class MessagerieAcces
{
    public const ROLES_DIRECTION = ['dg'];

    public static function estDirection(): bool
    {
        // Auth::hasAnyRole répond déjà oui pour un administrateur ; on le
        // dit quand même, pour que la règle se lise sans aller voir Auth.
        return Auth::isAdmin() || Auth::hasAnyRole(self::ROLES_DIRECTION);
    }

    /**
     * La même règle pour un compte qui n'est pas celui de la session : les
     * groupes automatiques (lot 5) en tirent les administrateurs du canal
     * « Annonces Direction » et de « Tout LBP ».
     *
     * @param array<int, string> $roles rôles de lbp_user_roles
     */
    public static function estDirectionCompte(bool $estAdmin, array $roles): bool
    {
        return $estAdmin || array_intersect(self::ROLES_DIRECTION, $roles) !== [];
    }
}
