<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Qui ouvre le contrôle des caisses.
 *
 * Décidé le 24/09/2026 : l'écran est celui de la surveillance, réservé à la
 * direction. Il montre, agence par agence, ce qui a été encaissé, ce qui a
 * été compté, ce qui ne l'a jamais été. Une agence qui verrait cet écran
 * saurait exactement ce que la direction regarde et quand — l'écran perdrait
 * son objet.
 *
 * Le comptable en est exclu lui aussi : il tient les comptes, il ne contrôle
 * pas les caisses. L'assistante du DG lit tout, comme partout ailleurs ;
 * l'écran est en lecture seule, elle n'y peut rien modifier par construction.
 */
final class ControleCaisseAcces
{
    /** @var array<int, string> */
    public const ROLES_LECTURE = ['dg', 'assistant_dg', 'assistante_dg', 'dg_surveillance'];

    public static function peutOuvrir(): bool
    {
        return Auth::isAdmin() || Auth::hasAnyRole(self::ROLES_LECTURE);
    }
}
