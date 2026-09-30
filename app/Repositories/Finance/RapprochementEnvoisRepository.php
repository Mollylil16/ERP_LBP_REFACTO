<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use PDO;

/**
 * Lecture des départs pour le rapprochement du comptable, et écriture de ce
 * qu'il saisit.
 *
 * La ligne rapprochée est le départ lui-même — l'expédition. Le rapprochement
 * partait du dossier d'envoi, et l'écran restait donc désespérément vide : les
 * agences groupent leurs colis par « Groupage & Expéditions », qui crée une
 * expédition sans ouvrir de dossier. Les filtres compagnie et agence, bâtis
 * sur les seuls départs rencontrés, se retrouvaient vides eux aussi.
 *
 * Les colis et le poids de l'agence sont repris du dossier lorsqu'il existe —
 * c'est le comptage figé au moment du départ — et calculés sinon à partir des
 * colis rattachés à l'expédition. La colonne se remplit donc toute seule, dans
 * les deux cas.
 *
 * Le rapprochement vit dans sa propre table : les colonnes du départ ne sont
 * jamais modifiées ici. Elles portent la saisie des agences et la lecture de
 * l'agent export, que la direction compare.
 */
final class RapprochementEnvoisRepository
{
    /** L'agence qui a expédié : celle de l'expédition, ou celle du dossier. */
    private const AGENCE_DEPART = 'COALESCE(e.agence_depart_id, d.agence_depart_id)';

    /** La compagnie : celle inscrite au départ, ou celle que le comptable a saisie. */
    private const COMPAGNIE = 'COALESCE(d.transporteur_id, r.transporteur_id)';

    /** La date du départ, du plus sûr au plus approximatif. */
    private const DATE_REFERENCE = 'COALESCE(d.date_depart_effective, e.date_depart_prevue, DATE(e.created_at))';

    /**
     * Les colonnes du rapprochement sont préfixées r_ : la ligne composée les
     * distingue ainsi de celles du départ, qui portent les mêmes noms.
     */
    private const SELECT = "
        SELECT e.id,
               d.id AS dossier_id,
               COALESCE(NULLIF(d.numero, ''), e.reference) AS numero,
               COALESCE(d.statut, e.statut) AS statut,
               COALESCE(d.mode_transport, e.type_transport) AS mode_transport,
               COALESCE(NULLIF(d.numero_document, ''), NULLIF(r.numero_document, '')) AS numero_document,
               d.nb_colis_declare, d.poids_brut_kg,
               COALESCE(d.colis_erp, c.colis) AS colis_erp,
               COALESCE(d.poids_erp_kg, c.poids) AS poids_erp_kg,
               d.taux_eur_xof,
               " . self::COMPAGNIE . " AS transporteur_id,
               " . self::AGENCE_DEPART . " AS agence_depart_id,
               " . self::DATE_REFERENCE . " AS date_reference,
               COALESCE(t.name, tr.name) AS transporteur,
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
               r.transporteur_id AS r_transporteur_id,
               r.numero_document AS r_numero_document,
               r.rapproche_le AS r_rapproche_le,
               rp.full_name AS r_rapproche_par_nom
        FROM lbp_expeditions e
        LEFT JOIN lbp_dossiers_envoi d ON d.expedition_id = e.id
        LEFT JOIN (
            SELECT expedition_id,
                   SUM(COALESCE(NULLIF(nombre_colis, 0), 1)) AS colis,
                   SUM(poids_total) AS poids
            FROM lbp_colis
            WHERE expedition_id IS NOT NULL
            GROUP BY expedition_id
        ) c ON c.expedition_id = e.id
        LEFT JOIN lbp_envois_rapprochement r ON r.expedition_id = e.id
        LEFT JOIN lbp_prestataires t ON t.id = d.transporteur_id
        LEFT JOIN lbp_prestataires tr ON tr.id = r.transporteur_id
        LEFT JOIN company_sites ad ON ad.id = COALESCE(e.agence_depart_id, d.agence_depart_id)
        LEFT JOIN company_sites aa ON aa.id = COALESCE(e.agence_arrivee_id, d.agence_arrivee_id)
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

        // MySQL n'accepte pas un alias du SELECT dans le WHERE : l'expression
        // est reprise telle quelle.
        if (!empty($filtres['du'])) {
            $conditions[] = self::DATE_REFERENCE . ' >= :du';
            $parametres['du'] = (string) $filtres['du'];
        }

        if (!empty($filtres['au'])) {
            $conditions[] = self::DATE_REFERENCE . ' <= :au';
            $parametres['au'] = (string) $filtres['au'];
        }

        if ((int) ($filtres['transporteur_id'] ?? 0) > 0) {
            $conditions[] = self::COMPAGNIE . ' = :transporteur';
            $parametres['transporteur'] = (int) $filtres['transporteur_id'];
        }

        if ((int) ($filtres['agence_id'] ?? 0) > 0) {
            $conditions[] = self::AGENCE_DEPART . ' = :agence';
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
            $conditions[] = '(d.numero_document LIKE :q1 OR d.numero LIKE :q2'
                . ' OR e.reference LIKE :q3 OR r.numero_document LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $marque) {
                $parametres[$marque] = '%' . $recherche . '%';
            }
        }

        $stmt = $this->pdo->prepare(
            self::SELECT . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY date_reference DESC, e.id DESC LIMIT ' . max(1, $limite)
        );
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function trouver(int $expeditionId): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE e.id = :id LIMIT 1');
        $stmt->execute(['id' => $expeditionId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Enregistre la saisie du comptable. Une seule ligne par départ : la
     * réécrire ne crée pas de doublon.
     *
     * @param array<string, mixed> $valeurs
     */
    public function enregistrer(int $expeditionId, ?int $dossierId, array $valeurs, int $userId): void
    {
        $colonnes = [
            'colis_lta', 'poids_lta_kg', 'motif_correction', 'poids_divers_kg', 'poids_perissable_kg',
            'montant_compagnie', 'devise_compagnie', 'taux_eur_xof', 'transporteur_id', 'numero_document',
            'mode_reglement', 'numero_cheque', 'date_reglement', 'observation',
        ];

        // PDO n'émule pas les marqueurs : le même acteur en occupe deux, il lui
        // faut donc deux noms.
        $parametres = ['expedition' => $expeditionId, 'dossier' => $dossierId, 'acteur' => $userId, 'auteur' => $userId];
        foreach ($colonnes as $colonne) {
            $parametres[$colonne] = $valeurs[$colonne] ?? null;
        }

        $insertion = implode(', ', $colonnes);
        $marques = implode(', ', array_map(static fn (string $c): string => ':' . $c, $colonnes));
        $miseAJour = implode(', ', array_map(static fn (string $c): string => $c . ' = VALUES(' . $c . ')', $colonnes));

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_envois_rapprochement (expedition_id, dossier_id, {$insertion}, rapproche_par, rapproche_le, created_by, created_at)
            VALUES (:expedition, :dossier, {$marques}, :acteur, NOW(), :auteur, NOW())
            ON DUPLICATE KEY UPDATE {$miseAJour},
                dossier_id = VALUES(dossier_id),
                rapproche_par = VALUES(rapproche_par),
                rapproche_le = VALUES(rapproche_le),
                updated_at = NOW()
        ");
        $stmt->execute($parametres);
    }

    /**
     * Les compagnies, toutes celles en activité.
     *
     * Elles étaient tirées des seuls départs déjà rencontrés : tant qu'aucun
     * départ ne portait de compagnie, le filtre s'ouvrait sur rien, et l'écran
     * paraissait cassé. Une liste de référence ne dépend pas de ce que le
     * logiciel a déjà vu.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function compagnies(): array
    {
        /*
         * Les compagnies de transport d'abord : le comptable rapproche des
         * factures de compagnie, pas de transitaire. Les autres prestataires
         * restent proposés — un transitaire repris de l'ancienne base porte un
         * type vide, et les écarter le ferait disparaître de la liste.
         */
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
        $lignes = $this->pdo->query("
            SELECT id, name FROM company_sites
            WHERE is_active = 1
            ORDER BY name
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'name' => (string) $l['name']], $lignes);
    }
}
