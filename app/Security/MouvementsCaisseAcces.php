<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Qui ouvre les mouvements de caisse, et qui y saisit.
 *
 * L'écran rassemble ce qui entre et ce qui sort d'un tiroir dans la journée.
 * Il est donc ouvert à qui tient la caisse — l'agent voit ce qu'il devra
 * compter le soir — et à qui la contrôle.
 *
 * La saisie, elle, ne sert qu'à ce que LBP ne connaît pas : un règlement de
 * facture et un approvisionnement y entrent tout seuls, et se corrigent à leur
 * source. Elle reste donc entre les mains de ceux qui tiennent et tiennent les
 * comptes de la caisse, pas de tout le personnel de l'agence.
 *
 * L'assistante DG lit tout et n'écrit rien, comme partout ailleurs.
 */
final class MouvementsCaisseAcces
{
    /** @var array<int, string> */
    public const ROLES_LECTURE = [
        'caissiere', 'caissiere_principale', 'gestionnaire_caisse', 'comptable',
        'chef_agence', 'agent_saisie', 'agent_enregistrement',
        'dg', 'assistant_dg', 'assistante_dg', 'dg_surveillance',
        'superviseur_general', 'superviseur_regional',
    ];

    /**
     * Qui saisit.
     *
     * Seule Abobo Dokui a une caissière : partout ailleurs, depuis la décision
     * du 17/09/2026, c'est l'agent de saisie qui facture, encaisse et soumet le
     * point de caisse. Lui refuser la saisie d'une entrée que LBP ne connaît
     * pas laisserait son tiroir en désaccord avec son propre comptage du soir,
     * dans toutes les agences sauf une.
     *
     * La portée reste celle de son agence : l'agent ne saisit que dans les
     * caisses qu'il tient, et chaque ligne garde son nom au journal d'audit.
     *
     * @var array<int, string>
     */
    public const ROLES_SAISIE = [
        'caissiere', 'caissiere_principale', 'gestionnaire_caisse', 'comptable',
        'agent_saisie', 'agent_enregistrement',
    ];

    /**
     * Qui regarde au-delà de son agence. Une caissière ne voit que son tiroir :
     * ce qui entre ailleurs ne la regarde pas, et le comparer la tromperait.
     *
     * @var array<int, string>
     */
    public const ROLES_RESEAU = [
        'comptable', 'caissiere_principale',
        'dg', 'assistant_dg', 'assistante_dg', 'dg_surveillance', 'superviseur_general',
        /*
         * Le responsable groupage general suit les trois agences de Cote
         * d Ivoire sans etre rattache a aucune. Il manquait ici alors qu il
         * figure dans Auth::ROLES_PORTEE_RESEAU, la liste de reference du
         * logiciel : deux listes qui devaient dire la meme chose.
         *
         * Sa portee est desormais nommee. Jusqu au 10/10/2026 il ne voyait au
         * dela de son agence que par le defaut de portee corrige le meme jour
         * — c est-a-dire par accident, et au prix d ouvrir la meme porte a
         * huit autres comptes sans agence.
         */
        'responsable_groupage', 'agent_exploitation',
    ];

    /**
     * @param array<int, string> $roles
     */
    public function __construct(
        private array $roles,
        private bool $admin,
        private ?int $userId,
        private ?int $agenceId
    ) {
    }

    public static function courant(): self
    {
        $utilisateur = Auth::user();

        return new self(
            $utilisateur?->roles ?? [],
            (bool) ($utilisateur?->isAdmin ?? false),
            $utilisateur?->id,
            $utilisateur?->agenceId
        );
    }

    /** La tuile du menu : elle ne s'affiche qu'à qui peut ouvrir l'écran. */
    public static function peutOuvrir(): bool
    {
        return self::courant()->peutLire();
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function agenceId(): ?int
    {
        return $this->agenceId;
    }

    public function peutLire(): bool
    {
        return $this->admin || $this->porte(self::ROLES_LECTURE);
    }

    public function peutSaisir(): bool
    {
        return $this->admin || $this->porte(self::ROLES_SAISIE);
    }

    public function voitToutesLesAgences(): bool
    {
        return $this->admin || $this->porte(self::ROLES_RESEAU);
    }

    /** @param array<int, string> $attendus */
    private function porte(array $attendus): bool
    {
        foreach ($attendus as $role) {
            if (in_array($role, $this->roles, true)) {
                return true;
            }
        }

        return false;
    }
}
