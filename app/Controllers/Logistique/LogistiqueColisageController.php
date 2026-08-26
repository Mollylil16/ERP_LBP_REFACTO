<?php

declare(strict_types=1);

namespace App\Controllers\Logistique;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Helpers\View;
use PDO;

final class LogistiqueColisageController extends LogistiqueBaseController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $selectedDate = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = date('Y-m-d');
        }

        // Selected agence filter (default: current logged in user agence if set, else 0/all)
        $userAgenceId = $_SESSION['user']['agence_id'] ?? null;
        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : ($userAgenceId ? (int) $userAgenceId : 0);

        // Fetch active agencies
        $sitesStmt = $this->pdo->query("SELECT id, name, code, city FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        $sites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch parcels for selected agence and date
        $parcels = $this->fetchColisData($selectedAgenceId, $selectedDate);

        // KPI aggregates (WITHOUT monetary values)
        $totalSaisies = count($parcels);
        $totalNombreColis = 0;
        $totalPoids = 0.0;

        foreach ($parcels as $p) {
            $totalNombreColis += (int) ($p['nombre_colis'] ?? 1);
            $totalPoids += (float) ($p['poids_total'] ?? 0.0);
        }

        $module = (new \App\Repositories\Logistique\LogistiqueDashboardRepository($this->pdo))->dashboard();

        $this->logistiqueView('logistique/colisage', 'Suivi Colisage Agences', 'colisage', $module, [
            'sites' => $sites,
            'selectedDate' => $selectedDate,
            'selectedAgenceId' => $selectedAgenceId,
            'parcels' => $parcels,
            'kpis' => [
                'totalSaisies' => $totalSaisies,
                'totalNombreColis' => $totalNombreColis,
                'totalPoids' => $totalPoids,
            ],
        ]);
    }

    public function exportPdf(): void
    {
        AuthMiddleware::check();

        $selectedDate = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = date('Y-m-d');
        }

        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : 0;
        $parcels = $this->fetchColisData($selectedAgenceId, $selectedDate);

        $agenceName = 'Toutes les agences';
        if ($selectedAgenceId > 0) {
            $stmt = $this->pdo->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $selectedAgenceId]);
            $agenceName = $stmt->fetchColumn() ?: 'Agence #' . $selectedAgenceId;
        }

        $totalSaisies = count($parcels);
        $totalNombreColis = array_sum(array_column($parcels, 'nombre_colis'));
        $totalPoids = array_sum(array_column($parcels, 'poids_total'));

        require BASE_PATH . '/views/logistique/colisage_pdf.php';
    }

    public function exportExcel(): void
    {
        AuthMiddleware::check();

        $selectedDate = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = date('Y-m-d');
        }

        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : 0;
        $parcels = $this->fetchColisData($selectedAgenceId, $selectedDate);

        $agenceName = 'Toutes les agences';
        if ($selectedAgenceId > 0) {
            $stmt = $this->pdo->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $selectedAgenceId]);
            $agenceName = $stmt->fetchColumn() ?: 'Agence #' . $selectedAgenceId;
        }

        $filename = 'suivi_colisage_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $agenceName) . '_' . $selectedDate . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        require BASE_PATH . '/views/logistique/colisage_excel.php';
    }

    /**
     * Fetch parcels for logistique consultation (STRICTLY WITHOUT MONETARY AMOUNTS).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchColisData(int $agenceId, string $date): array
    {
        $sql = "
            SELECT 
                c.id,
                c.numero_tracking,
                c.poids_total,
                c.nombre_colis,
                c.statut,
                c.trajet AS col_trajet,
                c.type_expediteur,
                c.created_at,
                exp.name AS expediteur_name,
                dest.name AS destinataire_name,
                dest.phone AS destinataire_phone,
                dep.name AS agence_depart_name,
                arr.name AS agence_arrivee_name,
                COALESCE(u.full_name, 'Agent') AS agent_name,
                t.code AS trajet_code,
                t.libelle AS trajet_libelle,
                t.type_transport AS trajet_transport
            FROM lbp_colis c
            LEFT JOIN lbp_clients exp ON c.expediteur_id = exp.id
            LEFT JOIN lbp_clients dest ON c.destinataire_id = dest.id
            LEFT JOIN company_sites dep ON c.agence_depart_id = dep.id
            LEFT JOIN company_sites arr ON c.agence_arrivee_id = arr.id
            LEFT JOIN users u ON c.agent_groupage_id = u.id
            LEFT JOIN trajets t ON c.trajet_id = t.id
            WHERE DATE(c.created_at) = :date
        ";

        $params = ['date' => $date];

        if ($agenceId > 0) {
            $sql .= " AND (c.agence_depart_id = :agence_id1 OR c.agence_arrivee_id = :agence_id2)";
            $params['agence_id1'] = $agenceId;
            $params['agence_id2'] = $agenceId;
        }

        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
