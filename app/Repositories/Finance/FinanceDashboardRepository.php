<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use PDO;

final class FinanceDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('finance');
    }

    /**
     * Get rich financial statistics for the dashboard
     *
     * @return array<string, mixed>
     */
    public function getFinanceStats(): array
    {
        // 1. Sum total facturé, encaissé et restant dû par devise
        $stmt = $this->pdo->query("
            SELECT devise,
                   COALESCE(SUM(montant_total), 0) as total_facture,
                   COALESCE(SUM(montant_encaisse), 0) as total_encaisse,
                   COALESCE(SUM(montant_restant), 0) as total_restant
            FROM lbp_factures
            WHERE statut <> 'annulee'
            GROUP BY devise
        ");
        $factureTotals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $kpis = [
            'facture_xof' => 0.0,
            'facture_eur' => 0.0,
            'encaisse_xof' => 0.0,
            'encaisse_eur' => 0.0,
            'restant_xof' => 0.0,
            'restant_eur' => 0.0,
        ];

        foreach ($factureTotals as $row) {
            $devise = strtoupper($row['devise']);
            if ($devise === 'XOF') {
                $kpis['facture_xof'] = (float) $row['total_facture'];
                $kpis['encaisse_xof'] = (float) $row['total_encaisse'];
                $kpis['restant_xof'] = (float) $row['total_restant'];
            } elseif ($devise === 'EUR') {
                $kpis['facture_eur'] = (float) $row['total_facture'];
                $kpis['encaisse_eur'] = (float) $row['total_encaisse'];
                $kpis['restant_eur'] = (float) $row['total_restant'];
            }
        }

        // 2. Count pending supplier payouts
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM lbp_demandes_paiement_prestataires WHERE statut = 'en_attente'
        ");
        $kpis['pending_payouts'] = (int) $stmt->fetchColumn();

        // 3. Count daily closures pending consolidation
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM lbp_etats_journaliers WHERE statut = 'soumis'
        ");
        $kpis['pending_closures'] = (int) $stmt->fetchColumn();

        return $kpis;
    }

    /**
     * Get recent invoices
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentFactures(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, c.name as client_name
            FROM lbp_factures f
            LEFT JOIN lbp_clients c ON f.client_id = c.id
            ORDER BY f.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get recent daily closures
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentEtats(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, s.name as agence_name
            FROM lbp_etats_journaliers e
            LEFT JOIN company_sites s ON e.agence_id = s.id
            ORDER BY e.date_jour DESC, e.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get recent double-entry accounting entries
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentEcritures(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM lbp_ecritures_comptables
            ORDER BY id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
