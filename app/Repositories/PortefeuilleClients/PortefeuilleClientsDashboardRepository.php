<?php

declare(strict_types=1);

namespace App\Repositories\PortefeuilleClients;

use PDO;
use Throwable;

/**
 * Valeur et risque de chaque client, reconstitués depuis la facturation.
 *
 * Il n'existe pas de table « portefeuille » alimentée : la valeur d'un client
 * se déduit de ses factures et de ses colis. On la calcule donc, plutôt que
 * d'ouvrir un référentiel commercial parallèle qui resterait à saisir à la main
 * et divergerait aussitôt de la facturation.
 *
 * Les montants restent séparés par devise : additionner des XOF et des EUR dans
 * une même colonne produirait un chiffre faux.
 */
class PortefeuilleClientsDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /** Factures qui comptent dans le chiffre d'affaires : une annulée n'est pas du CA. */
    private const FACTURES_REELLES = "f.statut IN ('emise', 'partiellement_payee', 'payee')";

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('portefeuille-clients');
    }

    /**
     * Un client par ligne, avec son activité sur la période demandée.
     *
     * @param int $mois profondeur d'historique retenue pour le chiffre d'affaires
     * @return array<int, array<string, mixed>>
     */
    public function clients(?int $agenceId, int $mois = 12, int $limite = 300): array
    {
        $mois = max(1, min($mois, 120));
        $limite = max(1, min($limite, 1000));
        $filtre = $agenceId !== null ? ' AND f.agence_id = :agence' : '';

        $sql = "
            SELECT
                cl.id,
                cl.name AS client,
                cl.phone,
                cl.email,
                cl.type,
                cl.created_at AS client_depuis,
                f.devise,
                COUNT(f.id) AS nb_factures,
                SUM(f.montant_total) AS ca,
                SUM(f.montant_encaisse) AS encaisse,
                SUM(f.montant_restant) AS impaye,
                MAX(f.date_emission) AS derniere_facture,
                MIN(f.date_emission) AS premiere_facture,
                SUM(CASE WHEN f.montant_restant > 0 THEN 1 ELSE 0 END) AS nb_impayees
            FROM lbp_clients cl
            INNER JOIN lbp_factures f ON f.client_id = cl.id
            WHERE " . self::FACTURES_REELLES . "
              AND f.date_emission >= DATE_SUB(CURDATE(), INTERVAL {$mois} MONTH)
              {$filtre}
            GROUP BY cl.id, cl.name, cl.phone, cl.email, cl.type, cl.created_at, f.devise
            ORDER BY ca DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence' => $agenceId] : []);
    }

    /**
     * Clients sans aucune facture depuis un certain temps, mais qui en ont eu :
    * ce sont eux qu'il est utile de rappeler.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inactifs(?int $agenceId, int $joursSansActivite = 90, int $limite = 100): array
    {
        $jours = max(7, min($joursSansActivite, 730));
        $limite = max(1, min($limite, 500));
        $filtre = $agenceId !== null ? ' AND f.agence_id = :agence' : '';

        $sql = "
            SELECT
                cl.id,
                cl.name AS client,
                cl.phone,
                cl.type,
                MAX(f.date_emission) AS derniere_facture,
                DATEDIFF(CURDATE(), MAX(f.date_emission)) AS jours_sans_activite,
                COUNT(f.id) AS nb_factures,
                f.devise,
                SUM(f.montant_total) AS ca_historique
            FROM lbp_clients cl
            INNER JOIN lbp_factures f ON f.client_id = cl.id
            WHERE " . self::FACTURES_REELLES . "
              {$filtre}
            GROUP BY cl.id, cl.name, cl.phone, cl.type, f.devise
            HAVING jours_sans_activite >= {$jours}
            ORDER BY ca_historique DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence' => $agenceId] : []);
    }

    /**
     * Encours impayé par client, avec l'ancienneté de la plus vieille facture
     * non soldée : c'est elle qui qualifie le risque, pas le montant seul.
     *
     * @return array<int, array<string, mixed>>
     */
    public function encours(?int $agenceId, int $limite = 100): array
    {
        $limite = max(1, min($limite, 500));
        $filtre = $agenceId !== null ? ' AND f.agence_id = :agence' : '';

        $sql = "
            SELECT
                cl.id,
                cl.name AS client,
                cl.phone,
                f.devise,
                COUNT(f.id) AS nb_factures_ouvertes,
                SUM(f.montant_restant) AS impaye,
                MIN(f.date_emission) AS plus_ancienne,
                DATEDIFF(CURDATE(), MIN(f.date_emission)) AS anciennete_jours
            FROM lbp_clients cl
            INNER JOIN lbp_factures f ON f.client_id = cl.id
            WHERE f.montant_restant > 0
              AND f.statut IN ('emise', 'partiellement_payee')
              {$filtre}
            GROUP BY cl.id, cl.name, cl.phone, f.devise
            ORDER BY impaye DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence' => $agenceId] : []);
    }

    /**
     * Nombre total de clients enregistrés, pour situer la part réellement
     * active dans le fichier.
     */
    public function nombreClients(): int
    {
        try {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM lbp_clients')->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
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
