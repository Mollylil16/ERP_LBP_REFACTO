<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Qui peut supprimer une fiche de l'effectif.
 *
 * Décidé le 17/11/2026 : l'administrateur, le directeur général et la
 * responsable RH. La suppression sert à effacer une fiche créée par erreur
 * (doublon, test, faute de saisie) ; un vrai départ passe toujours par
 * « Sortie », qui garde le dossier. Le service refuse d'ailleurs toute fiche
 * qui a déjà un historique réel (contrat, paie, pointage…).
 */
final class SuppressionPersonnel
{
    public const ROLES = ['dg', 'responsable_rh'];

    public static function autorise(): bool
    {
        return Auth::isAdmin() || Auth::hasAnyRole(self::ROLES);
    }
}
