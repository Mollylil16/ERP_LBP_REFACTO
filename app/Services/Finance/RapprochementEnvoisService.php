<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Database;
use App\Repositories\Finance\RapprochementEnvoisRepository;
use App\Security\RapprochementEnvoisAcces;
use App\Services\Finance\RapprochementEnvoisRegles as Regles;
use RuntimeException;

/**
 * Le rapprochement des envois, vu par le comptable.
 *
 * Le service assemble : il lit les départs, compose chaque ligne par les règles
 * et applique les filtres qui dépendent d'un état calculé — « écarts seulement »
 * ne peut pas se traduire en SQL, l'état n'existe pas en base.
 */
final class RapprochementEnvoisService
{
    public function __construct(private RapprochementEnvoisRepository $repo)
    {
    }

    public static function creer(): self
    {
        return new self(new RapprochementEnvoisRepository(Database::getConnection()));
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array<string, mixed>
     */
    public function tableau(array $filtres): array
    {
        $filtres = $this->filtres($filtres);

        $lignes = array_map(
            static fn (array $ligne): array => Regles::composer($ligne),
            $this->repo->lister($filtres)
        );

        if (!empty($filtres['ecarts_seulement'])) {
            $lignes = array_values(array_filter(
                $lignes,
                static fn (array $l): bool => (bool) ($l['ecart_colis']['depasse'] ?? false)
                    || (bool) ($l['ecart_poids']['depasse'] ?? false)
                    || $l['etat'] === 'LTA_A_COMPLETER'
            ));
        }

        return [
            'lignes' => $lignes,
            'totaux' => Regles::totaux($lignes),
            'compagnies' => $this->repo->compagnies(),
            'agences' => $this->repo->agences(),
            'filtres' => $filtres,
        ];
    }

    /** @return array<string, mixed>|null */
    public function ligne(int $dossierId): ?array
    {
        $brute = $this->repo->trouver($dossierId);

        return $brute === null ? null : Regles::composer($brute);
    }

    /**
     * Enregistre la saisie du comptable.
     *
     * @param array<string, mixed> $saisie
     * @return array{0:string, 1:array<int, string>} message, erreurs
     */
    public function enregistrer(int $dossierId, array $saisie, RapprochementEnvoisAcces $acces): array
    {
        if (!$acces->peutSaisir()) {
            return ['', ['Votre profil consulte le rapprochement sans le modifier.']];
        }

        $ligne = $this->ligne($dossierId);

        if ($ligne === null) {
            return ['', ['Ce départ est introuvable.']];
        }

        ['valeurs' => $valeurs, 'erreurs' => $erreurs] = Regles::lireSaisie($saisie, $ligne);

        if ($erreurs !== []) {
            return ['', $erreurs];
        }

        $userId = $acces->userId();

        if ($userId === null) {
            throw new RuntimeException('Session expirée.');
        }

        $this->repo->enregistrer($dossierId, $valeurs, $userId);

        return ['Rapprochement du départ ' . ($ligne['numero'] ?: (string) $dossierId) . ' enregistré.', []];
    }

    /**
     * Filtres normalisés. Par défaut le mois en cours : c'est la période que le
     * comptable rapproche.
     *
     * @param array<string, mixed> $filtres
     * @return array<string, mixed>
     */
    public function filtres(array $filtres): array
    {
        $date = static function (mixed $valeur, string $defaut): string {
            $texte = trim((string) ($valeur ?? ''));

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $texte) === 1 ? $texte : $defaut;
        };

        $du = $date($filtres['du'] ?? null, date('Y-m-01'));
        $au = $date($filtres['au'] ?? null, date('Y-m-d'));

        if ($du > $au) {
            [$du, $au] = [$au, $du];
        }

        $reglement = (string) ($filtres['reglement'] ?? '');

        return [
            'du' => $du,
            'au' => $au,
            'transporteur_id' => (int) ($filtres['transporteur_id'] ?? 0),
            'agence_id' => (int) ($filtres['agence_id'] ?? 0),
            'reglement' => in_array($reglement, ['REGLE', 'NON_REGLE'], true) ? $reglement : '',
            'q' => trim((string) ($filtres['q'] ?? '')),
            'ecarts_seulement' => !empty($filtres['ecarts_seulement']),
        ];
    }
}
