<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Qui valide une demande de fonds, et qui en sort l'argent.
 *
 * La regle etait recopiee dans cinq endroits du controleur. Elle a derive :
 * la liste reconnaissait le comptable, la fiche detaillee l'avait oublie, et
 * il lisait une demande sans pouvoir la trancher. On la tient ici, une fois.
 *
 * Partage decide le 14/10/2026, a la demande du comptable SIBRI PAUL et avec
 * l'accord de l'administrateur : l'assistante DG valide, le comptable
 * decaisse. Chacun un geste. Avant, l'assistante DG pouvait autoriser une
 * depense ET sortir l'argent sur la meme demande ; le comptable, lui, validait
 * sans jamais payer. Le doublon d'un cote, rien de l'autre.
 */
final class DemandesFondsAcces
{
    /**
     * Valider ou rejeter : autoriser la depense.
     *
     * Le comptable n'y est plus — c'est la contrepartie du decaissement qu'il
     * recupere. Il reste maitre de l'imputation, qui vient apres.
     */
    public const ROLES_VALIDATION = ['dg', 'assistant_dg', 'assistante_dg'];

    /**
     * Decaisser : sortir l'argent et editer le bon de sortie.
     *
     * La caissiere principale le garde : c'est elle qui tient physiquement
     * l'espece en agence, et la direction l'avait tranche le 05/10/2026. Le
     * comptable la rejoint ; l'assistante DG s'en retire, sans quoi le partage
     * n'en serait pas un.
     */
    public const ROLES_DECAISSEMENT = ['caissiere_principale', 'comptable', 'dg'];

    public static function peutValider(): bool
    {
        return Auth::isAdmin() || Auth::hasAnyRole(self::ROLES_VALIDATION);
    }

    public static function peutDecaisser(): bool
    {
        return Auth::isAdmin() || Auth::hasAnyRole(self::ROLES_DECAISSEMENT);
    }

    /**
     * Personne ne decaisse ce qu'il a lui-meme valide.
     *
     * Le DG et l'administrateur gardent les deux gestes, pour que rien ne
     * bloque un jour de conge. Ce garde-fou fait que ce cumul ne se referme
     * jamais sur une seule demande : deux mains, toujours. C'est la meme regle
     * que sur les demandes de fournitures.
     */
    public static function aDejaValide(?int $validateurId): bool
    {
        $moi = (int) Auth::id();

        return $moi > 0 && (int) $validateurId === $moi;
    }
}
