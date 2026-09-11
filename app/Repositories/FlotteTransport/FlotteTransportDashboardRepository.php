<?php

declare(strict_types=1);

namespace App\Repositories\FlotteTransport;

use PDO;
use Throwable;

/**
 * Lecture de la flotte : qui roule, avec quoi, sur quelle mission.
 *
 * Les véhicules ne sont pas dans une table dédiée : ils sont portés par le
 * livreur qui les conduit (lbp_livreurs.modele_vehicule, plaque_immatriculation),
 * et les missions sont les expéditions qui lui sont confiées. On lit donc là où
 * la donnée est réellement saisie, plutôt que d'ouvrir un second référentiel
 * véhicule qui resterait vide.
 */
class FlotteTransportDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /** Statuts d'expédition qui comptent comme une mission encore en cours. */
    private const MISSIONS_OUVERTES = "('BROUILLON', 'EN_PREPARATION', 'EN_TRANSIT')";

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('flotte-transport');
    }

    /**
     * Livreurs, leur véhicule, leur disponibilité et leur charge courante.
     *
     * Le rattachement d'agence passe par l'utilisateur : un livreur est un
     * compte, et c'est le compte qui porte l'agence.
     *
     * @return array<int, array<string, mixed>>
     */
    public function livreurs(?int $agenceId): array
    {
        $filtre = $agenceId !== null ? ' WHERE u.agence_id = :agence' : '';

        $sql = "
            SELECT
                l.id,
                l.modele_vehicule,
                l.plaque_immatriculation,
                l.statut,
                l.latitude,
                l.longitude,
                l.derniere_localisation,
                u.full_name AS livreur,
                u.phone,
                u.status AS statut_compte,
                s.name AS agence_name,
                COALESCE(m.missions_ouvertes, 0) AS missions_ouvertes
            FROM lbp_livreurs l
            INNER JOIN users u ON u.id = l.user_id
            LEFT JOIN company_sites s ON s.id = u.agence_id
            LEFT JOIN (
                SELECT livreur_id, COUNT(*) AS missions_ouvertes
                FROM lbp_expeditions
                WHERE livreur_id IS NOT NULL
                  AND statut IN " . self::MISSIONS_OUVERTES . "
                GROUP BY livreur_id
            ) m ON m.livreur_id = l.id
            {$filtre}
            ORDER BY u.full_name ASC
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence_depart' => $agenceId, 'agence_arrivee' => $agenceId] : []);
    }

    /**
     * Missions en cours, avec le nombre de colis embarqués et le retard éventuel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function missions(?int $agenceId, int $limite = 60): array
    {
        $limite = max(1, min($limite, 300));
        $filtre = $agenceId !== null ? ' AND (e.agence_depart_id = :agence_depart OR e.agence_arrivee_id = :agence_arrivee)' : '';

        $sql = "
            SELECT
                e.id,
                e.reference,
                e.type_transport,
                e.date_depart_prevue,
                e.date_arrivee_estimee,
                e.statut,
                dep.name AS agence_depart,
                arr.name AS agence_arrivee,
                u.full_name AS livreur,
                l.plaque_immatriculation,
                COALESCE(c.nb_colis, 0) AS nb_colis,
                COALESCE(c.poids_kg, 0) AS poids_kg,
                CASE
                    WHEN e.date_arrivee_estimee IS NOT NULL
                     AND e.date_arrivee_estimee < CURDATE()
                     AND e.statut IN " . self::MISSIONS_OUVERTES . "
                    THEN DATEDIFF(CURDATE(), e.date_arrivee_estimee)
                    ELSE 0
                END AS jours_retard
            FROM lbp_expeditions e
            LEFT JOIN company_sites dep ON dep.id = e.agence_depart_id
            LEFT JOIN company_sites arr ON arr.id = e.agence_arrivee_id
            LEFT JOIN lbp_livreurs l ON l.id = e.livreur_id
            LEFT JOIN users u ON u.id = l.user_id
            LEFT JOIN (
                SELECT expedition_id,
                       SUM(nombre_colis) AS nb_colis,
                       SUM(poids_total) AS poids_kg
                FROM lbp_colis
                WHERE expedition_id IS NOT NULL
                GROUP BY expedition_id
            ) c ON c.expedition_id = e.id
            WHERE e.statut IN " . self::MISSIONS_OUVERTES . "
            {$filtre}
            ORDER BY jours_retard DESC, e.date_arrivee_estimee ASC, e.id DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence_depart' => $agenceId, 'agence_arrivee' => $agenceId] : []);
    }

    /**
     * Missions clôturées récemment, pour juger de la tenue des délais.
     *
     * @return array<int, array<string, mixed>>
     */
    public function missionsTerminees(?int $agenceId, int $limite = 20): array
    {
        $limite = max(1, min($limite, 100));
        $filtre = $agenceId !== null ? ' AND (e.agence_depart_id = :agence_depart OR e.agence_arrivee_id = :agence_arrivee)' : '';

        $sql = "
            SELECT
                e.reference,
                e.type_transport,
                e.date_arrivee_estimee,
                e.updated_at,
                e.statut,
                dep.name AS agence_depart,
                arr.name AS agence_arrivee,
                u.full_name AS livreur
            FROM lbp_expeditions e
            LEFT JOIN company_sites dep ON dep.id = e.agence_depart_id
            LEFT JOIN company_sites arr ON arr.id = e.agence_arrivee_id
            LEFT JOIN lbp_livreurs l ON l.id = e.livreur_id
            LEFT JOIN users u ON u.id = l.user_id
            WHERE e.statut IN ('ARRIVE', 'CLOTURE')
            {$filtre}
            ORDER BY e.updated_at DESC, e.id DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence_depart' => $agenceId, 'agence_arrivee' => $agenceId] : []);
    }

    /**
     * @param array<string, mixed> $parametres
     * @return array<int, array<string, mixed>>
     */
    private function lignes(string $sql, array $parametres): array
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
