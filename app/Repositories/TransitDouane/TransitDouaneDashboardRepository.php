<?php

declare(strict_types=1);

namespace App\Repositories\TransitDouane;

use PDO;
use Throwable;

/**
 * Lecture des lots de dédouanement et de leur coût de revient.
 *
 * Finance saisit les coûts d'approche (/finance/couts-approche) ; ce module
 * regarde les mêmes lots sous l'angle du transitaire : où en est le lot, ce
 * qu'il pèse, et si son coût au kilo dérive par rapport aux autres lots du même
 * trajet. Aucune écriture, pour que la saisie reste au seul endroit où elle est
 * contrôlée.
 */
class TransitDouaneDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('transit-douane');
    }

    /**
     * Lots de dédouanement avec leur ventilation de frais.
     *
     * Le total est recalculé ici plutôt que repris de cout_par_kg_xof : cette
     * colonne est figée à la saisie et ne se met pas à jour si un frais est
     * corrigé ensuite.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lots(int $limite = 100): array
    {
        $limite = max(1, min($limite, 500));

        $sql = "
            SELECT
                lc.id,
                lc.reference_lot,
                lc.trajet_code,
                lc.frais_douane_xof,
                lc.frais_fret_xof,
                lc.frais_manutention_xof,
                lc.poids_total_kg,
                lc.cout_par_kg_xof AS cout_kg_saisi,
                lc.statut,
                lc.created_at,
                t.libelle AS trajet_libelle,
                t.type_transport,
                t.destination,
                (lc.frais_douane_xof + lc.frais_fret_xof + lc.frais_manutention_xof) AS total_xof
            FROM lbp_landed_costs lc
            LEFT JOIN trajets t ON t.code = lc.trajet_code
            ORDER BY lc.created_at DESC, lc.id DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql);
    }

    /**
     * Coût moyen au kilo par trajet, sur les lots validés ou clôturés
     * seulement : une simulation n'est pas une référence de coût.
     *
     * @return array<int, array<string, mixed>>
     */
    public function coutParTrajet(): array
    {
        $sql = "
            SELECT
                lc.trajet_code,
                t.libelle AS trajet_libelle,
                COUNT(*) AS nb_lots,
                SUM(lc.poids_total_kg) AS poids_kg,
                SUM(lc.frais_douane_xof) AS douane_xof,
                SUM(lc.frais_fret_xof) AS fret_xof,
                SUM(lc.frais_manutention_xof) AS manutention_xof,
                SUM(lc.frais_douane_xof + lc.frais_fret_xof + lc.frais_manutention_xof) AS total_xof
            FROM lbp_landed_costs lc
            LEFT JOIN trajets t ON t.code = lc.trajet_code
            WHERE lc.statut IN ('VALIDÉ', 'CLÔTURÉ')
            GROUP BY lc.trajet_code, t.libelle
            ORDER BY total_xof DESC
        ";

        return $this->lignes($sql);
    }

    /**
     * Volume encore en transit, par trajet : ce qui reste à dédouaner.
     *
     * @return array<int, array<string, mixed>>
     */
    public function colisEnTransit(?int $agenceId): array
    {
        $filtre = $agenceId !== null ? ' AND (c.agence_depart_id = :agence OR c.agence_arrivee_id = :agence)' : '';

        $sql = "
            SELECT
                COALESCE(NULLIF(c.trajet, ''), 'Non renseigné') AS trajet_code,
                t.libelle AS trajet_libelle,
                COUNT(*) AS nb_envois,
                SUM(c.nombre_colis) AS nb_colis,
                SUM(c.poids_total) AS poids_kg,
                SUM(c.valeur_declaree) AS valeur_declaree
            FROM lbp_colis c
            LEFT JOIN trajets t ON t.code = c.trajet
            WHERE c.statut IN ('EN_TRANSIT', 'EN_PRÉPARATION')
            {$filtre}
            GROUP BY trajet_code, t.libelle
            ORDER BY poids_kg DESC
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence' => $agenceId] : []);
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
        } catch (Throwable) {
            return [];
        }
    }
}
