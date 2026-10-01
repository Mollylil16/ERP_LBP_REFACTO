<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use PDO;

/**
 * Le rapprochement des envois, au grain du tableur que la direction tenait à
 * la main.
 *
 * Une ligne est un envoi : une date de départ, une compagnie, une LTA.
 *
 * Côté Abidjan, rien ne se saisit : le logiciel somme les colis des agences
 * qui ont chargé ce jour-là, par leur date de départ prévue. C'est le suivi de
 * colisage que chaque agence remet la veille ou le matin même, et il est déjà
 * dans le logiciel — le redemander serait le recopier.
 *
 * Côté compagnie, tout se saisit : à réception de la facture hebdomadaire, le
 * comptable inscrit ce que MENZIES a pesé à l'aéroport et ce qu'Air Côte
 * d'Ivoire facture. L'écart entre les deux est ce que la maison paie sans
 * l'avoir confié.
 *
 * Deux compagnies peuvent partir le même jour : l'envoi porte donc la liste
 * des agences qui y ont chargé, et la somme ne retient que celles-là.
 */
final class RapprochementEnvoisRepository
{
    /**
     * Les colonnes de l'envoi, préfixées r_ pour que la ligne composée les
     * distingue de celles qui viennent du terrain.
     */
    private const SELECT = "
        SELECT e.id,
               e.date_envoi AS date_reference,
               e.transporteur_id,
               e.numero_lta AS numero_document,
               t.name AS transporteur,
               e.colis_factures AS r_colis_lta,
               e.poids_facture_kg AS r_poids_lta_kg,
               e.poids_divers_kg AS r_poids_divers_kg,
               e.poids_perissable_kg AS r_poids_perissable_kg,
               e.montant_compagnie AS r_montant_compagnie,
               e.devise_compagnie AS r_devise_compagnie,
               e.taux_eur_xof AS r_taux_eur_xof,
               e.mode_reglement AS r_mode_reglement,
               e.numero_cheque AS r_numero_cheque,
               e.date_reglement AS r_date_reglement,
               e.motif_correction AS r_motif_correction,
               e.observation AS r_observation,
               e.numero_lta AS r_numero_document,
               e.rapproche_le AS r_rapproche_le,
               rp.full_name AS r_rapproche_par_nom
        FROM lbp_rappro_envois e
        LEFT JOIN lbp_prestataires t ON t.id = e.transporteur_id
        LEFT JOIN users rp ON rp.id = e.rapproche_par
    ";

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array<int, array<string, mixed>>
     */
    public function lister(array $filtres, int $limite = 500): array
    {
        $conditions = ['1 = 1'];
        $parametres = [];

        if (!empty($filtres['du'])) {
            $conditions[] = 'e.date_envoi >= :du';
            $parametres['du'] = (string) $filtres['du'];
        }

        if (!empty($filtres['au'])) {
            $conditions[] = 'e.date_envoi <= :au';
            $parametres['au'] = (string) $filtres['au'];
        }

        if ((int) ($filtres['transporteur_id'] ?? 0) > 0) {
            $conditions[] = 'e.transporteur_id = :transporteur';
            $parametres['transporteur'] = (int) $filtres['transporteur_id'];
        }

        if ((int) ($filtres['agence_id'] ?? 0) > 0) {
            $conditions[] = 'EXISTS (SELECT 1 FROM lbp_rappro_envois_agences a WHERE a.envoi_id = e.id AND a.agence_id = :agence)';
            $parametres['agence'] = (int) $filtres['agence_id'];
        }

        if (($filtres['reglement'] ?? '') === 'REGLE') {
            $conditions[] = 'e.date_reglement IS NOT NULL';
        }

        if (($filtres['reglement'] ?? '') === 'NON_REGLE') {
            $conditions[] = 'e.date_reglement IS NULL';
        }

        // PDO n'émule pas les marqueurs : un même nom ne peut pas servir deux fois.
        $recherche = trim((string) ($filtres['q'] ?? ''));
        if ($recherche !== '') {
            $conditions[] = '(e.numero_lta LIKE :q1 OR t.name LIKE :q2)';
            $parametres['q1'] = '%' . $recherche . '%';
            $parametres['q2'] = '%' . $recherche . '%';
        }

        $stmt = $this->pdo->prepare(
            self::SELECT . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY e.date_envoi DESC, e.id DESC LIMIT ' . max(1, $limite)
        );
        $stmt->execute($parametres);

        return $this->completer($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function trouver(int $envoiId): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE e.id = :id LIMIT 1');
        $stmt->execute(['id' => $envoiId]);

        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ligne === false) {
            return null;
        }

        return $this->completer([$ligne])[0];
    }

    /**
     * Ajoute à chaque envoi ce que les agences ont enregistré : le total, et le
     * détail agence par agence que la direction ouvre en cas d'écart.
     *
     * @param array<int, array<string, mixed>> $envois
     * @return array<int, array<string, mixed>>
     */
    private function completer(array $envois): array
    {
        foreach ($envois as $index => $envoi) {
            $agences = $this->agencesDeLEnvoi((int) $envoi['id']);
            $saisie = $this->saisieDesAgences((string) $envoi['date_reference'], array_column($agences, 'id'));

            $envois[$index]['agences'] = $agences;
            $envois[$index]['detail_agences'] = $saisie['detail'];
            $envois[$index]['colis_erp'] = $saisie['colis'];
            $envois[$index]['poids_erp_kg'] = $saisie['poids'];
            $envois[$index]['agence_depart'] = implode(', ', array_column($agences, 'name'));
        }

        return $envois;
    }

    /** @return array<int, array{id:int, name:string}> */
    public function agencesDeLEnvoi(int $envoiId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT s.id, s.name
            FROM lbp_rappro_envois_agences a
            JOIN company_sites s ON s.id = a.agence_id
            WHERE a.envoi_id = :envoi
            ORDER BY s.name
        ');
        $stmt->execute(['envoi' => $envoiId]);

        return array_map(
            static fn (array $l): array => ['id' => (int) $l['id'], 'name' => (string) $l['name']],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    /**
     * Ce que les agences ont enregistré pour un départ : leurs colis et leur
     * poids, par leur date de départ prévue.
     *
     * Un envoi sans agence cochée ne compte rien : mieux vaut une colonne vide
     * qu'un total pris ailleurs.
     *
     * @param array<int, int> $agenceIds
     * @return array{colis:?int, poids:?float, detail:array<int, array<string, mixed>>}
     */
    public function saisieDesAgences(string $date, array $agenceIds): array
    {
        $agenceIds = array_values(array_filter(array_map('intval', $agenceIds)));

        if ($agenceIds === []) {
            return ['colis' => null, 'poids' => null, 'detail' => []];
        }

        $marques = implode(',', array_fill(0, count($agenceIds), '?'));

        $stmt = $this->pdo->prepare("
            SELECT c.agence_depart_id AS agence_id,
                   s.name AS agence,
                   SUM(COALESCE(NULLIF(c.nombre_colis, 0), 1)) AS colis,
                   SUM(c.poids_total) AS poids,
                   COUNT(*) AS saisies
            FROM lbp_colis c
            LEFT JOIN company_sites s ON s.id = c.agence_depart_id
            WHERE COALESCE(c.date_depart_prevue, DATE(c.created_at)) = ?
              AND c.agence_depart_id IN ({$marques})
            GROUP BY c.agence_depart_id, s.name
            ORDER BY s.name
        ");
        $stmt->execute(array_merge([$date], $agenceIds));

        $detail = [];
        $colis = 0;
        $poids = 0.0;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ligne) {
            $detail[] = [
                'agence_id' => (int) $ligne['agence_id'],
                'agence' => (string) ($ligne['agence'] ?? '—'),
                'colis' => (int) $ligne['colis'],
                'poids' => round((float) $ligne['poids'], 1),
                'saisies' => (int) $ligne['saisies'],
            ];

            $colis += (int) $ligne['colis'];
            $poids += (float) $ligne['poids'];
        }

        return ['colis' => $colis, 'poids' => round($poids, 1), 'detail' => $detail];
    }

    /**
     * Crée un envoi : la date, la compagnie, et les agences qui ont chargé.
     *
     * @param array<int, int> $agenceIds
     */
    public function creer(string $date, ?int $transporteurId, ?string $numeroLta, array $agenceIds, int $userId): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO lbp_rappro_envois (date_envoi, transporteur_id, numero_lta, cree_par, created_at)
            VALUES (:date, :transporteur, :lta, :user, NOW())
        ');
        $stmt->execute([
            'date' => $date,
            'transporteur' => $transporteurId,
            'lta' => $numeroLta,
            'user' => $userId,
        ]);

        $envoiId = (int) $this->pdo->lastInsertId();
        $this->remplacerAgences($envoiId, $agenceIds);

        return $envoiId;
    }

    /** @param array<int, int> $agenceIds */
    public function remplacerAgences(int $envoiId, array $agenceIds): void
    {
        $this->pdo->prepare('DELETE FROM lbp_rappro_envois_agences WHERE envoi_id = :envoi')
            ->execute(['envoi' => $envoiId]);

        $insert = $this->pdo->prepare('INSERT IGNORE INTO lbp_rappro_envois_agences (envoi_id, agence_id) VALUES (:envoi, :agence)');

        foreach (array_unique(array_map('intval', $agenceIds)) as $agenceId) {
            if ($agenceId > 0) {
                $insert->execute(['envoi' => $envoiId, 'agence' => $agenceId]);
            }
        }
    }

    /**
     * Enregistre ce que la facture de la compagnie annonce.
     *
     * @param array<string, mixed> $valeurs
     */
    public function enregistrer(int $envoiId, array $valeurs, int $userId): void
    {
        $colonnes = [
            'transporteur_id', 'numero_lta', 'colis_factures', 'poids_facture_kg',
            'poids_divers_kg', 'poids_perissable_kg',
            'montant_compagnie', 'devise_compagnie', 'taux_eur_xof',
            'mode_reglement', 'numero_cheque', 'date_reglement',
            'motif_correction', 'observation',
        ];

        // Seules les colonnes fournies sont ecrites : une saisie partielle ne
        // doit pas effacer la compagnie ou la LTA inscrites a l'ouverture.
        $colonnes = array_values(array_filter(
            $colonnes,
            static fn (string $c): bool => array_key_exists($c, $valeurs)
        ));

        if ($colonnes === []) {
            return;
        }

        $affectations = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, $colonnes));

        $parametres = ['envoi' => $envoiId, 'acteur' => $userId];
        foreach ($colonnes as $colonne) {
            $parametres[$colonne] = $valeurs[$colonne];
        }

        $stmt = $this->pdo->prepare("
            UPDATE lbp_rappro_envois
               SET {$affectations}, rapproche_par = :acteur, rapproche_le = NOW(), updated_at = NOW()
             WHERE id = :envoi
        ");
        $stmt->execute($parametres);

        if (isset($valeurs['agences']) && is_array($valeurs['agences'])) {
            $this->remplacerAgences($envoiId, $valeurs['agences']);
        }
    }

    public function supprimer(int $envoiId): void
    {
        $this->pdo->prepare('DELETE FROM lbp_rappro_envois_agences WHERE envoi_id = :envoi')->execute(['envoi' => $envoiId]);
        $this->pdo->prepare('DELETE FROM lbp_rappro_envois WHERE id = :envoi')->execute(['envoi' => $envoiId]);
    }

    /**
     * Les compagnies, toutes celles en activité, celles de transport d'abord.
     *
     * Elles étaient tirées des seuls départs déjà rencontrés : tant qu'aucun
     * départ ne portait de compagnie, le filtre s'ouvrait sur rien.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function compagnies(): array
    {
        $lignes = $this->pdo->query("
            SELECT id, name FROM lbp_prestataires
            WHERE is_active = 1
            ORDER BY (type IN ('COMPAGNIE_AERIENNE', 'COMPAGNIE_MARITIME', 'TRANSPORTEUR_ROUTIER', 'EXPRESS')) DESC,
                     name
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'name' => (string) $l['name']], $lignes);
    }

    /** @return array<int, array{id:int, name:string}> */
    public function agences(): array
    {
        $lignes = $this->pdo->query('
            SELECT id, name FROM company_sites
            WHERE is_active = 1
            ORDER BY name
        ')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'name' => (string) $l['name']], $lignes);
    }

    /**
     * Ce que les agences ont enregistré un jour donné, qu'un envoi ait été créé
     * ou non : c'est ce que le comptable lit avant de cocher ses agences.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suiviDuJour(string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.id AS agence_id, s.name AS agence,
                   SUM(COALESCE(NULLIF(c.nombre_colis, 0), 1)) AS colis,
                   SUM(c.poids_total) AS poids
            FROM lbp_colis c
            JOIN company_sites s ON s.id = c.agence_depart_id
            WHERE COALESCE(c.date_depart_prevue, DATE(c.created_at)) = :date
            GROUP BY s.id, s.name
            ORDER BY s.name
        ");
        $stmt->execute(['date' => $date]);

        return array_map(
            static fn (array $l): array => [
                'agence_id' => (int) $l['agence_id'],
                'agence' => (string) $l['agence'],
                'colis' => (int) $l['colis'],
                'poids' => round((float) $l['poids'], 1),
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }
}
