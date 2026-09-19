<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use PDO;

/**
 * Lecture des départs pour le rapprochement du comptable, et écriture de ce
 * qu'il saisit.
 *
 * Le rapprochement vit dans sa propre table : les colonnes du départ ne sont
 * jamais modifiées ici. Elles portent la saisie des agences et la lecture de
 * l'agent export, que la direction compare.
 */
final class RapprochementEnvoisRepository
{
    /**
     * Les colonnes du rapprochement sont préfixées r_ : la ligne composée les
     * distingue ainsi de celles du départ, qui portent les mêmes noms.
     */
    private const SELECT = "
        SELECT d.id, d.numero, d.statut, d.mode_transport,
               d.numero_document, d.nb_colis_declare, d.poids_brut_kg,
               d.colis_erp, d.poids_erp_kg, d.taux_eur_xof,
               d.transporteur_id, d.agence_depart_id,
               COALESCE(d.date_depart_effective, DATE(d.created_at)) AS date_reference,
               t.name AS transporteur,
               ad.name AS agence_depart, aa.name AS agence_arrivee,
               r.colis_lta AS r_colis_lta,
               r.poids_lta_kg AS r_poids_lta_kg,
               r.motif_correction AS r_motif_correction,
               r.poids_divers_kg AS r_poids_divers_kg,
               r.poids_perissable_kg AS r_poids_perissable_kg,
               r.montant_compagnie AS r_montant_compagnie,
               r.devise_compagnie AS r_devise_compagnie,
               r.taux_eur_xof AS r_taux_eur_xof,
               r.mode_reglement AS r_mode_reglement,
               r.numero_cheque AS r_numero_cheque,
               r.date_reglement AS r_date_reglement,
               r.observation AS r_observation,
               r.rapproche_le AS r_rapproche_le,
               rp.full_name AS r_rapproche_par_nom
        FROM lbp_dossiers_envoi d
        LEFT JOIN lbp_envois_rapprochement r ON r.dossier_id = d.id
        LEFT JOIN lbp_prestataires t ON t.id = d.transporteur_id
        LEFT JOIN company_sites ad ON ad.id = d.agence_depart_id
        LEFT JOIN company_sites aa ON aa.id = d.agence_arrivee_id
        LEFT JOIN users rp ON rp.id = r.rapproche_par
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
            $conditions[] = 'COALESCE(d.date_depart_effective, DATE(d.created_at)) >= :du';
            $parametres['du'] = (string) $filtres['du'];
        }

        if (!empty($filtres['au'])) {
            $conditions[] = 'COALESCE(d.date_depart_effective, DATE(d.created_at)) <= :au';
            $parametres['au'] = (string) $filtres['au'];
        }

        if ((int) ($filtres['transporteur_id'] ?? 0) > 0) {
            $conditions[] = 'd.transporteur_id = :transporteur';
            $parametres['transporteur'] = (int) $filtres['transporteur_id'];
        }

        if ((int) ($filtres['agence_id'] ?? 0) > 0) {
            $conditions[] = 'd.agence_depart_id = :agence';
            $parametres['agence'] = (int) $filtres['agence_id'];
        }

        if (($filtres['reglement'] ?? '') === 'REGLE') {
            $conditions[] = 'r.date_reglement IS NOT NULL';
        }

        if (($filtres['reglement'] ?? '') === 'NON_REGLE') {
            $conditions[] = 'r.date_reglement IS NULL';
        }

        // PDO n'émule pas les marqueurs : un même nom ne peut pas servir deux fois.
        $recherche = trim((string) ($filtres['q'] ?? ''));
        if ($recherche !== '') {
            $conditions[] = '(d.numero_document LIKE :q1 OR d.numero LIKE :q2)';
            $parametres['q1'] = '%' . $recherche . '%';
            $parametres['q2'] = '%' . $recherche . '%';
        }

        $stmt = $this->pdo->prepare(
            self::SELECT . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY date_reference DESC, d.id DESC LIMIT ' . max(1, $limite)
        );
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function trouver(int $dossierId): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE d.id = :id LIMIT 1');
        $stmt->execute(['id' => $dossierId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Enregistre la saisie du comptable. Une seule ligne par départ : la
     * réécrire ne crée pas de doublon.
     *
     * @param array<string, mixed> $valeurs
     */
    public function enregistrer(int $dossierId, array $valeurs, int $userId): void
    {
        $colonnes = [
            'colis_lta', 'poids_lta_kg', 'motif_correction', 'poids_divers_kg', 'poids_perissable_kg',
            'montant_compagnie', 'devise_compagnie', 'taux_eur_xof',
            'mode_reglement', 'numero_cheque', 'date_reglement', 'observation',
        ];

        // PDO n'émule pas les marqueurs : le même acteur en occupe deux, il lui
        // faut donc deux noms.
        $parametres = ['dossier' => $dossierId, 'acteur' => $userId, 'auteur' => $userId];
        foreach ($colonnes as $colonne) {
            $parametres[$colonne] = $valeurs[$colonne] ?? null;
        }

        $insertion = implode(', ', $colonnes);
        $marques = implode(', ', array_map(static fn (string $c): string => ':' . $c, $colonnes));
        $miseAJour = implode(', ', array_map(static fn (string $c): string => $c . ' = VALUES(' . $c . ')', $colonnes));

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_envois_rapprochement (dossier_id, {$insertion}, rapproche_par, rapproche_le, created_by, created_at)
            VALUES (:dossier, {$marques}, :acteur, NOW(), :auteur, NOW())
            ON DUPLICATE KEY UPDATE {$miseAJour},
                rapproche_par = VALUES(rapproche_par),
                rapproche_le = VALUES(rapproche_le),
                updated_at = NOW()
        ");
        $stmt->execute($parametres);
    }

    /**
     * Compagnies qui ont effectivement emporté un départ : la liste du filtre
     * ne propose pas des prestataires jamais utilisés.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function compagnies(): array
    {
        $lignes = $this->pdo->query("
            SELECT DISTINCT t.id, t.name
            FROM lbp_dossiers_envoi d
            JOIN lbp_prestataires t ON t.id = d.transporteur_id
            ORDER BY t.name
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'name' => (string) $l['name']], $lignes);
    }

    /** @return array<int, array{id:int, name:string}> */
    public function agences(): array
    {
        $lignes = $this->pdo->query("
            SELECT DISTINCT s.id, s.name
            FROM lbp_dossiers_envoi d
            JOIN company_sites s ON s.id = d.agence_depart_id
            ORDER BY s.name
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'name' => (string) $l['name']], $lignes);
    }
}
