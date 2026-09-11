<?php

declare(strict_types=1);

namespace App\Repositories\TrackingColis;

use PDO;
use Throwable;

/**
 * Suivi d'un colis, vu de l'intérieur.
 *
 * Le site public répond « où est mon colis » avec le statut courant. Ici on
 * réunit tout ce qui s'est passé sur ce colis, quelle que soit la table qui l'a
 * enregistré : points GPS, mouvements de rayon, retrait au comptoir.
 *
 * L'écran expose aussi les recherches faites sur le site public qui n'ont rien
 * trouvé : chacune est un client resté sans réponse, donc un appel au call
 * center qui arrive ou une référence mal communiquée.
 */
class TrackingColisDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /** Statuts qui désignent un colis encore sous notre responsabilité. */
    private const EN_COURS = "('RÉCEPTIONNÉ', 'EN_PRÉPARATION', 'EN_TRANSIT', 'ARRIVÉ')";

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('tracking-colis');
    }

    /**
     * Colis actifs avec leur dernier événement connu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function colisActifs(?int $agenceId, string $recherche = '', int $limite = 100): array
    {
        $limite = max(1, min($limite, 500));

        $conditions = ['c.statut IN ' . self::EN_COURS];
        $parametres = [];

        if ($agenceId !== null) {
            $conditions[] = '(c.agence_depart_id = :agence_depart OR c.agence_arrivee_id = :agence_arrivee)';
            $parametres['agence_depart'] = $agenceId;
            $parametres['agence_arrivee'] = $agenceId;
        }

        if ($recherche !== '') {
            $conditions[] = '(c.numero_tracking LIKE :q OR expe.name LIKE :q OR dest.name LIKE :q OR dest.phone LIKE :q)';
            $parametres['q'] = '%' . $recherche . '%';
        }

        $where = implode(' AND ', $conditions);

        $sql = "
            SELECT
                c.id,
                c.numero_tracking,
                c.statut,
                c.poids_total,
                c.nombre_colis,
                c.trajet,
                c.created_at,
                expe.name AS expediteur,
                dest.name AS destinataire,
                dest.phone AS destinataire_tel,
                dep.name AS agence_depart,
                arr.name AS agence_arrivee,
                g.etape AS derniere_etape,
                g.date_etape AS derniere_etape_le,
                DATEDIFF(NOW(), c.created_at) AS jours_depuis_prise_en_charge
            FROM lbp_colis c
            LEFT JOIN lbp_clients expe ON expe.id = c.expediteur_id
            LEFT JOIN lbp_clients dest ON dest.id = c.destinataire_id
            LEFT JOIN company_sites dep ON dep.id = c.agence_depart_id
            LEFT JOIN company_sites arr ON arr.id = c.agence_arrivee_id
            LEFT JOIN (
                SELECT t.colis_id, t.etape, t.date_etape
                FROM lbp_tracking_gps t
                INNER JOIN (
                    SELECT colis_id, MAX(id) AS dernier_id
                    FROM lbp_tracking_gps
                    WHERE colis_id IS NOT NULL
                    GROUP BY colis_id
                ) d ON d.dernier_id = t.id
            ) g ON g.colis_id = c.id
            WHERE {$where}
            ORDER BY c.created_at DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql, $parametres);
    }

    /**
     * Un colis par sa référence exacte, pour afficher sa fiche.
     *
     * @return array<string, mixed>|null
     */
    public function colisParReference(string $reference): ?array
    {
        $sql = "
            SELECT
                c.*,
                expe.name AS expediteur,
                expe.phone AS expediteur_tel,
                dest.name AS destinataire,
                dest.phone AS destinataire_tel,
                dep.name AS agence_depart,
                arr.name AS agence_arrivee
            FROM lbp_colis c
            LEFT JOIN lbp_clients expe ON expe.id = c.expediteur_id
            LEFT JOIN lbp_clients dest ON dest.id = c.destinataire_id
            LEFT JOIN company_sites dep ON dep.id = c.agence_depart_id
            LEFT JOIN company_sites arr ON arr.id = c.agence_arrivee_id
            WHERE c.numero_tracking = :reference
            LIMIT 1
        ";

        $lignes = $this->lignes($sql, ['reference' => $reference]);

        return $lignes[0] ?? null;
    }

    /**
     * Tous les événements connus d'un colis, sources confondues, du plus récent
     * au plus ancien.
     *
     * @return array<int, array<string, mixed>>
     */
    public function evenements(int $colisId): array
    {
        $sql = "
            SELECT 'GPS' AS source, t.etape AS libelle, t.date_etape AS survenu_le,
                   t.latitude, t.longitude, NULL AS auteur, NULL AS detail
            FROM lbp_tracking_gps t
            WHERE t.colis_id = :colis_gps

            UNION ALL

            SELECT 'MAGASIN' AS source,
                   CONCAT(m.type_mouvement, COALESCE(CONCAT(' — ', r.code_rayon), '')) AS libelle,
                   m.created_at AS survenu_le,
                   NULL AS latitude, NULL AS longitude,
                   u.full_name AS auteur,
                   m.commentaires AS detail
            FROM logistique_mouvements_rayon m
            LEFT JOIN logistique_rayons r ON r.id = m.rayon_id
            LEFT JOIN users u ON u.id = m.effectue_par
            WHERE m.colis_id = :colis_mvt

            ORDER BY survenu_le DESC
        ";

        return $this->lignes($sql, ['colis_gps' => $colisId, 'colis_mvt' => $colisId]);
    }

    /**
     * Recherches faites sur le site public et restées sans résultat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recherchesInfructueuses(int $jours = 30, int $limite = 50): array
    {
        $jours = max(1, min($jours, 365));
        $limite = max(1, min($limite, 200));

        $sql = "
            SELECT
                r.reference,
                COUNT(*) AS nb_tentatives,
                MAX(r.created_at) AS derniere_tentative,
                COUNT(DISTINCT r.requester_ip) AS nb_visiteurs,
                CASE WHEN c.id IS NULL THEN 0 ELSE 1 END AS existe_aujourdhui
            FROM shipment_tracking_requests r
            LEFT JOIN lbp_colis c ON c.numero_tracking = r.reference
            WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL {$jours} DAY)
              AND (r.result_status IS NULL OR r.result_status NOT IN ('found', 'ok', 'success'))
            GROUP BY r.reference, existe_aujourdhui
            ORDER BY nb_tentatives DESC, derniere_tentative DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql);
    }

    /**
     * Volume de consultations publiques, pour situer les échecs.
     *
     * @return array{total:int, echecs:int}
     */
    public function volumeRecherches(int $jours = 30): array
    {
        $jours = max(1, min($jours, 365));

        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN result_status IS NULL OR result_status NOT IN ('found', 'ok', 'success') THEN 1 ELSE 0 END) AS echecs
            FROM shipment_tracking_requests
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$jours} DAY)
        ";

        $lignes = $this->lignes($sql);

        return [
            'total' => (int) ($lignes[0]['total'] ?? 0),
            'echecs' => (int) ($lignes[0]['echecs'] ?? 0),
        ];
    }

    /**
     * Répartition des colis actifs par statut.
     *
     * @return array<int, array<string, mixed>>
     */
    public function repartitionStatuts(?int $agenceId): array
    {
        $filtre = $agenceId !== null ? ' AND (c.agence_depart_id = :agence_depart OR c.agence_arrivee_id = :agence_arrivee)' : '';

        $sql = "
            SELECT c.statut,
                   COUNT(*) AS nb_envois,
                   SUM(c.nombre_colis) AS nb_colis,
                   SUM(c.poids_total) AS poids_kg,
                   AVG(DATEDIFF(NOW(), c.created_at)) AS age_moyen_jours
            FROM lbp_colis c
            WHERE 1 = 1
            {$filtre}
            GROUP BY c.statut
            ORDER BY nb_envois DESC
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence_depart' => $agenceId, 'agence_arrivee' => $agenceId] : []);
    }

    /**
     * @param array<string, mixed> $parametres
     * @return array<int, array<string, mixed>>
     */
    private function lignes(string $sql, array $parametres = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametres);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            /*
             * Une table absente ne doit pas faire tomber tout l'ecran : la
             * section concernee s'affiche vide et les autres restent lisibles.
             * Mais une requete fautive produit exactement le meme silence. On la
             * trace, sinon un tableau vide se lit comme « aucune donnee » alors
             * que la requete n'est jamais partie.
             */
            error_log('[LBP] Lecture impossible dans ' . static::class . ' : ' . $e->getMessage());

            return [];
        }
    }
}
