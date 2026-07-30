<?php

declare(strict_types=1);

namespace App\Repositories\Colisage;

use PDO;
use Throwable;

final class ColisageDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $data = $this->dashboardFor('colisage');
        
        $receptioned = 0;
        $inTransit = 0;
        $arrived = 0;
        $withdrawn = 0;
        $clientsCount = 0;

        try {
            $receptioned = (int) $this->pdo->query("SELECT COUNT(*) FROM lbp_colis WHERE statut = 'RÉCEPTIONNÉ'")->fetchColumn();
            $inTransit = (int) $this->pdo->query("SELECT COUNT(*) FROM lbp_expeditions WHERE statut = 'EN_TRANSIT'")->fetchColumn();
            $arrived = (int) $this->pdo->query("SELECT COUNT(*) FROM lbp_colis WHERE statut = 'ARRIVÉ'")->fetchColumn();
            $withdrawn = (int) $this->pdo->query("SELECT COUNT(*) FROM lbp_colis WHERE statut IN ('RETIRÉ', 'LIVRÉ')")->fetchColumn();
            $clientsCount = (int) $this->pdo->query("SELECT COUNT(*) FROM lbp_clients")->fetchColumn();
        } catch (Throwable $e) {
            // Safe fallback in case table structure varies
        }

        $data['kpis'] = [
            ['label' => 'Colis réceptionnés', 'value' => (string) $receptioned, 'meta' => 'En attente de groupage', 'href' => 'colisage/parcels?statut=RÉCEPTIONNÉ'],
            ['label' => 'Voyages en transit', 'value' => (string) $inTransit, 'meta' => 'Manifestes en cours', 'href' => 'colisage/groupage'],
            ['label' => 'Colis arrivés', 'value' => (string) $arrived, 'meta' => 'À retirer en agence', 'href' => 'colisage/parcels?statut=ARRIVÉ'],
            ['label' => 'Total livrés', 'value' => (string) $withdrawn, 'meta' => 'Remis aux destinataires', 'tone' => 'success', 'href' => 'colisage/parcels?statut=RETIRÉ'],
        ];

        // Fetch recent parcels with LEFT JOINs
        try {
            $stmtParcels = $this->pdo->query("
                SELECT c.*, cli_exp.name AS expediteur_name, cli_dest.name AS destinataire_name
                FROM lbp_colis c
                LEFT JOIN lbp_clients cli_exp ON c.expediteur_id = cli_exp.id
                LEFT JOIN lbp_clients cli_dest ON c.destinataire_id = cli_dest.id
                ORDER BY c.id DESC
                LIMIT 5
            ");
            $data['recentParcels'] = $stmtParcels ? ($stmtParcels->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            $data['recentParcels'] = [];
        }

        // Fetch recent manifestes with LEFT JOINs
        try {
            $stmtExp = $this->pdo->query("
                SELECT e.*, s_dep.name AS agence_depart_name, s_arr.name AS agence_arrivee_name
                FROM lbp_expeditions e
                LEFT JOIN company_sites s_dep ON e.agence_depart_id = s_dep.id
                LEFT JOIN company_sites s_arr ON e.agence_arrivee_id = s_arr.id
                ORDER BY e.id DESC
                LIMIT 5
            ");
            $data['recentExpeditions'] = $stmtExp ? ($stmtExp->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            $data['recentExpeditions'] = [];
        }
        
        $data['clientsCount'] = $clientsCount;

        return $data;
    }
}
