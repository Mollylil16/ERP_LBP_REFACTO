<?php

declare(strict_types=1);

namespace App\Controllers\Facturation;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Helpers\View;
use App\Helpers\Auth;
use App\Security\PermissionEntityRegistry;
use PDO;

final class FacturationFilterController extends BaseController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $startMonth = isset($_GET['start_month']) ? (int) $_GET['start_month'] : $currentMonth;
        $startYear = isset($_GET['start_year']) ? (int) $_GET['start_year'] : $currentYear;
        $endMonth = isset($_GET['end_month']) ? (int) $_GET['end_month'] : $currentMonth;
        $endYear = isset($_GET['end_year']) ? (int) $_GET['end_year'] : $currentYear;

        $startMonth = max(1, min(12, $startMonth));
        $endMonth = max(1, min(12, $endMonth));

        // Agency restriction for non-responsable agents
        $userAgenceId = $_SESSION['user']['agence_id'] ?? null;
        $canSeeAllAgencies = Auth::isAdmin() 
            || Auth::can(PermissionEntityRegistry::CONSULTER_TOUTES_FACTURES_TOUTES_AGENCES)
            || Auth::hasAnyRole(['responsable', 'chef_agence', 'superviseur_general', 'dg', 'comptable']);

        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : ($canSeeAllAgencies ? 0 : ($userAgenceId ? (int)$userAgenceId : 0));
        if (!$canSeeAllAgencies && $userAgenceId) {
            $selectedAgenceId = (int) $userAgenceId; // Force agent agence
        }

        $selectedTrajet = trim((string) ($_GET['trajet'] ?? 'all'));

        // Build date range (handles cross-year periods)
        $startDate = sprintf('%04d-%02d-01 00:00:00', $startYear, $startMonth);
        $lastDayOfEndMonth = date('t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
        $endDate = sprintf('%04d-%02d-%02d 23:59:59', $endYear, $endMonth, (int)$lastDayOfEndMonth);

        // Active agences
        $sitesStmt = $this->pdo->query("SELECT id, name, code FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        $sites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Active trajets
        $trajetsStmt = $this->pdo->query("SELECT * FROM trajets WHERE actif = 1 ORDER BY code ASC");
        $trajets = $trajetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch factures
        $results = $this->fetchFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedTrajet);

        // Aggregate statistics (WITH Amounts)
        $totalCount = count($results);
        $totalMontantXof = 0.0;
        $totalPoids = 0.0;
        $totalColis = 0;

        foreach ($results as $r) {
            $totalMontantXof += (float) ($r['montant_total'] ?? 0.0);
            $totalPoids += (float) ($r['poids_total'] ?? 0.0);
            $totalColis += (int) ($r['nombre_colis'] ?? 1);
        }

        $this->renderView('facturation/filtre', [
            'pageTitle' => 'Recherche & Filtres Facturation',
            'startMonth' => $startMonth,
            'startYear' => $startYear,
            'endMonth' => $endMonth,
            'endYear' => $endYear,
            'selectedAgenceId' => $selectedAgenceId,
            'selectedTrajet' => $selectedTrajet,
            'canSeeAllAgencies' => $canSeeAllAgencies,
            'sites' => $sites,
            'trajets' => $trajets,
            'results' => $results,
            'kpis' => [
                'totalCount' => $totalCount,
                'totalMontantXof' => $totalMontantXof,
                'totalPoids' => $totalPoids,
                'totalColis' => $totalColis,
            ],
        ]);
    }

    public function exportPdf(): void
    {
        AuthMiddleware::check();

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $startMonth = isset($_GET['start_month']) ? (int) $_GET['start_month'] : $currentMonth;
        $startYear = isset($_GET['start_year']) ? (int) $_GET['start_year'] : $currentYear;
        $endMonth = isset($_GET['end_month']) ? (int) $_GET['end_month'] : $currentMonth;
        $endYear = isset($_GET['end_year']) ? (int) $_GET['end_year'] : $currentYear;

        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : 0;
        $selectedTrajet = trim((string) ($_GET['trajet'] ?? 'all'));

        $startDate = sprintf('%04d-%02d-01 00:00:00', $startYear, $startMonth);
        $lastDay = date('t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
        $endDate = sprintf('%04d-%02d-%02d 23:59:59', $endYear, $endMonth, (int)$lastDay);

        $results = $this->fetchFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedTrajet);

        $agenceLabel = 'Toutes les agences';
        if ($selectedAgenceId > 0) {
            $stmt = $this->pdo->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $selectedAgenceId]);
            $agenceLabel = $stmt->fetchColumn() ?: 'Agence #' . $selectedAgenceId;
        }

        require BASE_PATH . '/views/facturation/filtre_pdf.php';
    }

    public function exportExcel(): void
    {
        AuthMiddleware::check();

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $startMonth = isset($_GET['start_month']) ? (int) $_GET['start_month'] : $currentMonth;
        $startYear = isset($_GET['start_year']) ? (int) $_GET['start_year'] : $currentYear;
        $endMonth = isset($_GET['end_month']) ? (int) $_GET['end_month'] : $currentMonth;
        $endYear = isset($_GET['end_year']) ? (int) $_GET['end_year'] : $currentYear;

        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : 0;
        $selectedTrajet = trim((string) ($_GET['trajet'] ?? 'all'));

        $startDate = sprintf('%04d-%02d-01 00:00:00', $startYear, $startMonth);
        $lastDay = date('t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
        $endDate = sprintf('%04d-%02d-%02d 23:59:59', $endYear, $endMonth, (int)$lastDay);

        $results = $this->fetchFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedTrajet);

        $agenceLabel = 'Toutes les agences';
        if ($selectedAgenceId > 0) {
            $stmt = $this->pdo->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $selectedAgenceId]);
            $agenceLabel = $stmt->fetchColumn() ?: 'Agence #' . $selectedAgenceId;
        }

        $filename = 'facturation_filtree_' . $startMonth . '_' . $startYear . '_a_' . $endMonth . '_' . $endYear . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        require BASE_PATH . '/views/facturation/filtre_excel.php';
    }

    private function fetchFilteredFactures(string $startDate, string $endDate, int $agenceId, string $trajetCode): array
    {
        $sql = "
            SELECT 
                f.id AS facture_id,
                f.numero_facture,
                f.date_emission,
                f.montant_total,
                f.montant_encaisse,
                f.montant_restant,
                f.devise,
                f.statut AS facture_statut,
                f.locked,
                f.locked_at,
                c.numero_tracking,
                c.poids_total,
                c.nombre_colis,
                c.type_expediteur,
                c.trajet AS col_trajet,
                cl.name AS client_name,
                cl.phone AS client_phone,
                s.name AS agence_name,
                s.code AS agence_code,
                COALESCE(u.full_name, 'Agent') AS agent_name,
                t.code AS trajet_code,
                t.libelle AS trajet_libelle,
                t.type_transport AS trajet_type_transport,
                (SELECT COUNT(*) FROM factures_audit_log fal WHERE fal.facture_id = f.id) AS modifications_count
            FROM lbp_factures f
            JOIN lbp_colis c ON f.colis_id = c.id
            JOIN lbp_clients cl ON f.client_id = cl.id
            JOIN company_sites s ON f.agence_id = s.id
            LEFT JOIN users u ON COALESCE(f.created_by, f.agent_id, f.caissiere_id) = u.id
            LEFT JOIN trajets t ON COALESCE(f.trajet_id, c.trajet_id) = t.id
            WHERE f.date_emission >= :start_date AND f.date_emission <= :end_date
        ";

        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($agenceId > 0) {
            $sql .= " AND f.agence_id = :agence_id";
            $params['agence_id'] = $agenceId;
        }

        if ($trajetCode !== '' && $trajetCode !== 'all') {
            $sql .= " AND (t.code = :trajet_code1 OR c.trajet = :trajet_code2)";
            $params['trajet_code1'] = $trajetCode;
            $params['trajet_code2'] = $trajetCode;
        }

        $sql .= " ORDER BY f.date_emission DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function renderView(string $view, array $data): void
    {
        $layoutData = [
            'pageTitle' => $data['pageTitle'] ?? 'Facturation',
            'moduleName' => 'Facturation',
            'moduleCode' => 'FAC',
            'activeModule' => 'factures',
            'additionalStyles' => ['css/finea-ui.css'],
        ];

        $data = array_merge(\App\Support\ViewBag::defaults(), $layoutData, $data);
        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/views/' . $view . '.php';
        $content = ob_get_clean();

        require BASE_PATH . '/views/layouts/module.php';
    }
}
