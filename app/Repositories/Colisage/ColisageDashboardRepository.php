<?php

declare(strict_types=1);

namespace App\Repositories\Colisage;

use PDO;
use Throwable;

/**
 * Le tableau de bord du colisage, sur les chiffres du jour et du mois.
 *
 * Trois de ses quatre compteurs affichaient zéro en permanence : ils
 * interrogeaient des statuts qui n'existent pas. La table connaît
 * « enregistre », « en_transit », « arrive », « livre », « retire » ; les
 * requêtes cherchaient « RÉCEPTIONNÉ ». Un écran de pilotage qui affiche zéro
 * là où le réseau travaille n'est pas seulement inutile : il fait douter du
 * reste.
 */
final class ColisageDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /** Ce qui compte comme colis vivant : tout sauf l'annulé. */
    private const COLIS_VIVANT = "c.statut <> 'annule'";

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $data = $this->dashboardFor('colisage');

        $moisDebut = date('Y-m-01');
        $aujourdhui = date('Y-m-d');

        $mois = $this->compteur("DATE(c.created_at) >= :debut", ['debut' => $moisDebut]);
        $jour = $this->compteur('DATE(c.created_at) = :jour', ['jour' => $aujourdhui]);
        $transit = $this->compteur("c.statut = 'en_transit'");
        $arrives = $this->compteur("c.statut = 'arrive'");
        $remis = $this->compteur("c.statut IN ('livre', 'retire') AND DATE(c.created_at) >= :debut", ['debut' => $moisDebut]);

        $voyages = $this->valeur("SELECT COUNT(*) FROM lbp_expeditions WHERE statut IN ('EN_PREPARATION', 'EN_TRANSIT')");

        $data['kpis'] = [
            [
                'label' => 'Colis enregistrés ce mois',
                'value' => $this->nombre($mois['colis']),
                'meta' => $this->nombre($jour['colis']) . " aujourd'hui · " . $this->nombre($mois['poids'], 0) . ' kg',
                'href' => 'colisage/parcels',
            ],
            [
                'label' => 'En transit',
                'value' => $this->nombre($transit['colis']),
                'meta' => $this->nombre((float) $voyages) . ' voyage(s) en cours',
                'href' => 'colisage/groupage',
            ],
            [
                'label' => 'Arrivés à retirer',
                'value' => $this->nombre($arrives['colis']),
                'meta' => 'En attente du destinataire',
                'tone' => $arrives['colis'] > 0 ? 'warning' : 'neutral',
                'href' => 'colisage/parcels?statut=arrive',
            ],
            [
                'label' => 'Remis ce mois',
                'value' => $this->nombre($remis['colis']),
                'meta' => 'Livrés ou retirés en agence',
                'tone' => 'success',
                'href' => 'colisage/parcels?statut=retire',
            ],
        ];

        $data['activiteAgences'] = $this->activiteParAgence($moisDebut);
        $data['activiteTrafics'] = $this->activiteParTrafic($moisDebut);
        $data['moisLibelle'] = $this->moisLibelle($moisDebut);

        $data['recentParcels'] = $this->lignes("
            SELECT c.*, cli_exp.name AS expediteur_name, cli_dest.name AS destinataire_name
            FROM lbp_colis c
            LEFT JOIN lbp_clients cli_exp ON c.expediteur_id = cli_exp.id
            LEFT JOIN lbp_clients cli_dest ON c.destinataire_id = cli_dest.id
            ORDER BY c.id DESC
            LIMIT 5
        ");

        $data['recentExpeditions'] = $this->lignes("
            SELECT e.*, s_dep.name AS agence_depart_name, s_arr.name AS agence_arrivee_name
            FROM lbp_expeditions e
            LEFT JOIN company_sites s_dep ON e.agence_depart_id = s_dep.id
            LEFT JOIN company_sites s_arr ON e.agence_arrivee_id = s_arr.id
            ORDER BY e.id DESC
            LIMIT 5
        ");

        $data['clientsCount'] = (int) $this->valeur('SELECT COUNT(*) FROM lbp_clients');

        return $data;
    }

    // ------------------------------------------------------------------

    /**
     * Colis, poids et montant d'un sous-ensemble.
     *
     * Le nombre de colis est la somme des colis déclarés, pas le nombre de
     * lignes : un enregistrement peut en porter dix.
     *
     * @param array<string, mixed> $parametres
     * @return array{colis:float, poids:float, montant:float}
     */
    private function compteur(string $condition, array $parametres = []): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(COALESCE(NULLIF(c.nombre_colis, 0), 1)), 0) AS colis,
                       COALESCE(SUM(c.poids_total), 0) AS poids,
                       COALESCE(SUM(c.montant_total), 0) AS montant
                FROM lbp_colis c
                WHERE " . self::COLIS_VIVANT . " AND {$condition}
            ");
            $stmt->execute($parametres);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'colis' => (float) ($ligne['colis'] ?? 0),
                'poids' => (float) ($ligne['poids'] ?? 0),
                'montant' => (float) ($ligne['montant'] ?? 0),
            ];
        } catch (Throwable $e) {
            return ['colis' => 0.0, 'poids' => 0.0, 'montant' => 0.0];
        }
    }

    /**
     * Ce que chaque agence a enregistré ce mois-ci.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activiteParAgence(string $depuis): array
    {
        $lignes = $this->lignes("
            SELECT s.name AS agence,
                   COALESCE(SUM(COALESCE(NULLIF(c.nombre_colis, 0), 1)), 0) AS colis,
                   COALESCE(SUM(c.poids_total), 0) AS poids,
                   COALESCE(SUM(c.montant_total), 0) AS montant,
                   COUNT(*) AS saisies
            FROM lbp_colis c
            JOIN company_sites s ON s.id = c.agence_depart_id
            WHERE " . self::COLIS_VIVANT . " AND DATE(c.created_at) >= :depuis
            GROUP BY s.id, s.name
            ORDER BY colis DESC
        ", ['depuis' => $depuis]);

        return array_map(static fn (array $l): array => [
            'agence' => (string) $l['agence'],
            'colis' => (int) $l['colis'],
            'poids' => round((float) $l['poids'], 1),
            'montant' => (float) $l['montant'],
            'saisies' => (int) $l['saisies'],
        ], $lignes);
    }

    /**
     * Et sur quelles lignes ces colis partent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activiteParTrafic(string $depuis): array
    {
        $lignes = $this->lignes("
            SELECT COALESCE(NULLIF(c.trafic, ''), NULLIF(c.trajet, ''), 'Non précisé') AS trafic,
                   COALESCE(SUM(COALESCE(NULLIF(c.nombre_colis, 0), 1)), 0) AS colis,
                   COALESCE(SUM(c.poids_total), 0) AS poids
            FROM lbp_colis c
            WHERE " . self::COLIS_VIVANT . " AND DATE(c.created_at) >= :depuis
            GROUP BY COALESCE(NULLIF(c.trafic, ''), NULLIF(c.trajet, ''), 'Non précisé')
            ORDER BY colis DESC
            LIMIT 6
        ", ['depuis' => $depuis]);

        return array_map(static fn (array $l): array => [
            'trafic' => (string) $l['trafic'],
            'colis' => (int) $l['colis'],
            'poids' => round((float) $l['poids'], 1),
        ], $lignes);
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
            // Un tableau de bord ne tombe pas : il montre ce qu'il peut.
            return [];
        }
    }

    private function valeur(string $sql): float
    {
        try {
            return (float) $this->pdo->query($sql)->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    private function nombre(float $valeur, int $decimales = 0): string
    {
        return number_format($valeur, $decimales, ',', ' ');
    }

    private function moisLibelle(string $date): string
    {
        $mois = ['01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril',
            '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août',
            '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'];

        return ($mois[substr($date, 5, 2)] ?? '') . ' ' . substr($date, 0, 4);
    }
}
