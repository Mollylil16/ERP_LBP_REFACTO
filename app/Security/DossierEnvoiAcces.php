<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;
use App\Services\Colisage\DossierEnvoiRegles;

/**
 * Qui peut faire quoi sur les départs préparés par l'agent export.
 *
 * Décidé le 15/09/2026 :
 * - l'agent export seul prépare un départ, le complète et le soumet ;
 * - le Directeur général voit tout, compare le document de la compagnie à la
 *   saisie des colis, valide ou renvoie. Lui seul voit les chiffres de la
 *   saisie : l'agent ne doit pas pouvoir les recopier ;
 * - personne d'autre, pas même l'assistant DG. L'administrateur garde un
 *   accès technique complet.
 *
 * La décision ne lit que les rôles : elle se teste sans session ni base.
 */
final class DossierEnvoiAcces
{
    public const ROLE_AGENT = 'agent_export';
    public const ROLE_VALIDEUR = 'dg';

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

    public function estAgent(): bool
    {
        return $this->admin || in_array(self::ROLE_AGENT, $this->roles, true);
    }

    public function estValideur(): bool
    {
        return $this->admin || in_array(self::ROLE_VALIDEUR, $this->roles, true);
    }

    public function peutOuvrir(): bool
    {
        return $this->estAgent() || $this->estValideur();
    }

    public function voitTout(): bool
    {
        return $this->estValideur();
    }

    /** Poids et nombre de colis de la saisie : le contrôle du Directeur général. */
    public function voitSaisie(): bool
    {
        return $this->estValideur();
    }

    /** Responsable imposé aux listes : null quand l'utilisateur voit tout. */
    public function responsableImpose(): ?int
    {
        return $this->voitTout() ? null : ($this->userId ?? 0);
    }

    public function peutCreer(): bool
    {
        return $this->estAgent();
    }

    /** @param array<string, mixed> $dossier */
    public function peutVoir(array $dossier): bool
    {
        return $this->voitTout() || ($this->estAgent() && $this->estResponsable($dossier));
    }

    /** @param array<string, mixed> $dossier */
    public function peutModifier(array $dossier): bool
    {
        return $this->estAgent()
            && ($this->admin || $this->estResponsable($dossier))
            && in_array((string) ($dossier['statut'] ?? ''), DossierEnvoiRegles::STATUTS_MODIFIABLES, true);
    }

    /** @param array<string, mixed> $dossier */
    public function peutSoumettre(array $dossier): bool
    {
        return $this->peutModifier($dossier);
    }

    /** @param array<string, mixed> $dossier */
    public function peutValider(array $dossier): bool
    {
        return $this->estValideur() && ($dossier['statut'] ?? '') === 'SOUMIS';
    }

    /** @param array<string, mixed> $dossier */
    public function peutRenvoyer(array $dossier): bool
    {
        return $this->peutValider($dossier);
    }

    /** @param array<string, mixed> $dossier */
    public function peutRouvrir(array $dossier): bool
    {
        return $this->estValideur() && ($dossier['statut'] ?? '') === 'VALIDE';
    }

    /** @param array<string, mixed> $dossier */
    private function estResponsable(array $dossier): bool
    {
        return $this->userId !== null && (int) ($dossier['responsable_id'] ?? 0) === $this->userId;
    }
}
