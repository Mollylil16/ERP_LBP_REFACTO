<?php

namespace App\Modules\Administration\Repositories;

use App\Models\Database;
use PDO;

class DashboardRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getGlobalStats(): array
    {
        $stats = [
            'total_users' => 0,
            'total_colis' => 0,
            'total_factures' => 0,
            'chiffre_affaires' => 0,
        ];

        // Utilisateurs
        $stmtUsers = $this->pdo->query('SELECT COUNT(*) FROM lbp_users');
        if ($stmtUsers) {
            $stats['total_users'] = (int) $stmtUsers->fetchColumn();
        }

        // Colis
        $stmtColis = $this->pdo->query('SELECT COUNT(*) FROM lbp_colis');
        if ($stmtColis) {
            $stats['total_colis'] = (int) $stmtColis->fetchColumn();
        }

        // Factures
        $stmtFactures = $this->pdo->query('SELECT COUNT(*) FROM lbp_factures');
        if ($stmtFactures) {
            $stats['total_factures'] = (int) $stmtFactures->fetchColumn();
        }

        // Chiffre d'affaires
        $stmtCA = $this->pdo->query('SELECT SUM(montant_total) FROM lbp_factures WHERE statut = \'PAYEE\'');
        if ($stmtCA) {
            $stats['chiffre_affaires'] = (float) $stmtCA->fetchColumn();
        }

        return $stats;
    }

    public function fetchTrackingLogs(): array
    {
        $sql = "
            SELECT 
                l.id,
                l.latitude,
                l.longitude,
                l.ip_address,
                l.date_connexion,
                u.fullname,
                u.username,
                ap.nom_agence AS agence_principale,
                asess.nom_agence AS agence_session
            FROM lbp_user_locations_log l
            JOIN lbp_users u ON l.id_user = u.id
            LEFT JOIN lbp_agences ap ON u.id_agence = ap.id
            LEFT JOIN lbp_agences asess ON l.agence_id_session = asess.id
            ORDER BY l.date_connexion DESC
            LIMIT 100
        ";

        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
