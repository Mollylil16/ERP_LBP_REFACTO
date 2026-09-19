<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Qui ouvre le rapprochement des envois, et qui y saisit.
 *
 * Décidé le 18/09/2026 avec la direction. L'écran montre les chiffres de saisie
 * des agences, que la règle du 15/09 réservait au Directeur général pour que
 * l'agent export ne puisse pas les recopier sur la LTA. Il est donc ouvert au
 * comptable, à la direction et à son assistante — aucun d'eux ne prépare de
 * départ — et reste fermé aux agences et à l'agent export.
 *
 * L'assistante DG lit tout et n'écrit rien, comme partout ailleurs.
 */
final class RapprochementEnvoisAcces
{
    /** @var array<int, string> */
    public const ROLES_LECTURE = ['comptable', 'dg', 'assistant_dg', 'assistante_dg'];

    /** @var array<int, string> */
    public const ROLES_SAISIE = ['comptable', 'dg'];

    /**
     * @param array<int, string> $roles
     */
    public function __construct(private array $roles, private bool $admin, private ?int $userId)
    {
    }

    public static function courant(): self
    {
        $utilisateur = Auth::user();

        return new self($utilisateur?->roles ?? [], (bool) ($utilisateur?->isAdmin ?? false), $utilisateur?->id);
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function peutOuvrir(): bool
    {
        return $this->admin || $this->porte(self::ROLES_LECTURE);
    }

    public function peutSaisir(): bool
    {
        // L'assistante DG porte assistant_dg : elle lit, elle ne corrige pas.
        return $this->admin || $this->porte(self::ROLES_SAISIE);
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
