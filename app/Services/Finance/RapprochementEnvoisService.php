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
            // Ce que les agences ont enregistré pour la date proposée : le
            // comptable le lit avant de cocher ses agences.
            'suiviDuJour' => $this->repo->suiviDuJour($filtres['au']),
        ];
    }

    /**
     * Ouvre un envoi : une date, une compagnie, les agences qui ont chargé.
     *
     * @param array<string, mixed> $saisie
     * @return array{0:string, 1:array<int, string>}
     */
    public function ouvrirEnvoi(array $saisie, RapprochementEnvoisAcces $acces): array
    {
        if (!$acces->peutSaisir()) {
            return ['', ['Votre profil consulte le rapprochement sans le modifier.']];
        }

        $date = trim((string) ($saisie['date_envoi'] ?? ''));
        $agences = is_array($saisie['agences'] ?? null) ? array_map('intval', $saisie['agences']) : [];
        $transporteur = (int) ($saisie['transporteur_id'] ?? 0);
        $lta = trim((string) ($saisie['numero_lta'] ?? ''));

        $erreurs = [];

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $erreurs[] = "La date de l'envoi est obligatoire : c'est elle qui rassemble les colis des agences.";
        }

        if ($agences === []) {
            $erreurs[] = 'Cochez au moins une agence : sans elles, la colonne « colis enregistrés » resterait vide.';
        }

        if ($erreurs !== []) {
            return ['', $erreurs];
        }

        $userId = $acces->userId();

        if ($userId === null) {
            throw new RuntimeException('Session expirée.');
        }

        $this->repo->creer($date, $transporteur > 0 ? $transporteur : null, $lta === '' ? null : $lta, $agences, $userId);

        return ['Envoi du ' . $date . ' ouvert. Les colis des agences cochées y sont repris.', []];
    }

    /**
     * @return array{0:string, 1:array<int, string>}
     */
    public function supprimer(int $envoiId, RapprochementEnvoisAcces $acces): array
    {
        if (!$acces->peutSaisir()) {
            return ['', ['Votre profil consulte le rapprochement sans le modifier.']];
        }

        if ($this->ligne($envoiId) === null) {
            return ['', ['Cet envoi est introuvable.']];
        }

        $this->repo->supprimer($envoiId);

        return ['Envoi supprimé.', []];
    }

    /** @return array<string, mixed>|null */
    public function ligne(int $envoiId): ?array
    {
        $brute = $this->repo->trouver($envoiId);

        return $brute === null ? null : Regles::composer($brute);
    }

    /**
     * Enregistre d un coup les lignes saisies a meme le tableau.
     *
     * Le comptable depouille une facture hebdomadaire : il remplit six ou dix
     * lignes d affilee, comme dans son tableur, puis enregistre une fois. Une
     * ligne refusee n empeche pas les autres de passer — sans quoi une faute de
     * frappe sur la derniere ligne ferait perdre tout le depouillement.
     *
     * @param array<int|string, array<string, mixed>> $lignes
     * @return array{0:string, 1:array<int, string>} message, erreurs
     */
    public function enregistrerLot(array $lignes, RapprochementEnvoisAcces $acces): array
    {
        if (!$acces->peutSaisir()) {
            return ['', ['Votre profil consulte le rapprochement sans le modifier.']];
        }

        $enregistrees = 0;
        $erreurs = [];

        foreach ($lignes as $id => $saisie) {
            $envoiId = (int) $id;

            if ($envoiId <= 0 || !is_array($saisie)) {
                continue;
            }

            [, $erreursLigne] = $this->enregistrer($envoiId, $saisie, $acces);

            if ($erreursLigne === []) {
                $enregistrees++;

                continue;
            }

            // Nommer le jour : avec dix lignes a l ecran, « le poids ne peut
            // pas etre negatif » ne dit pas laquelle refuse.
            $ligne = $this->ligne($envoiId);
            $jour = $ligne === null ? ('envoi #' . $envoiId) : ('envoi du ' . $this->jour((string) $ligne['date']));

            foreach ($erreursLigne as $erreur) {
                $erreurs[] = ucfirst($jour) . ' : ' . $erreur;
            }
        }

        if ($enregistrees === 0) {
            return ['', $erreurs === [] ? ['Aucune ligne modifiee : rien a enregistrer.'] : $erreurs];
        }

        $message = $enregistrees === 1
            ? '1 envoi rapproche.'
            : $enregistrees . ' envois rapproches.';

        return [$message, $erreurs];
    }

    /**
     * Enregistre la saisie du comptable.
     *
     * @param array<string, mixed> $saisie
     * @return array{0:string, 1:array<int, string>} message, erreurs
     */
    public function enregistrer(int $envoiId, array $saisie, RapprochementEnvoisAcces $acces): array
    {
        if (!$acces->peutSaisir()) {
            return ['', ['Votre profil consulte le rapprochement sans le modifier.']];
        }

        $ligne = $this->ligne($envoiId);

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

        if (isset($saisie['agences']) && is_array($saisie['agences'])) {
            $valeurs['agences'] = array_map('intval', $saisie['agences']);
        }

        $this->repo->enregistrer($envoiId, $valeurs, $userId);

        return ["Rapprochement de l'envoi du " . $this->jour((string) $ligne['date']) . ' enregistré.', []];
    }

    private function jour(string $date): string
    {
        $objet = date_create($date);

        return $objet === false ? $date : $objet->format('d/m/Y');
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
