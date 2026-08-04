<?php

declare(strict_types=1);

namespace App\Controllers\Facturation;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Security\PermissionEntityRegistry;
use PDO;

final class FacturationFilterController extends FacturationBaseController
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

        [$canSeeAllAgencies, $selectedAgenceId] = $this->resolveAgenceFilter();

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

        // Aggregate statistics over the WHOLE filtered range (not just the current page)
        $agg = $this->aggregateFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedTrajet);
        $totalCount = (int) $agg['total_count'];

        $perPage = 50;
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        $page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;

        $results = $this->fetchFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedTrajet, $perPage, $offset);

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
                'totalMontantXof' => (float) $agg['total_montant'],
                'totalPoids' => (float) $agg['total_poids'],
                'totalColis' => (int) $agg['total_colis'],
            ],
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'itemsPerPage' => $perPage,
                'totalItems' => $totalCount,
            ],
        ]);
    }

    public function exportPdf(): void
    {
        AuthMiddleware::check();

        if (!Auth::isAdmin() && !Auth::isFacturationPrivileged() && !Auth::can(PermissionEntityRegistry::EXPORTER_FACTURATION_AVEC_MONTANT)) {
            Session::flash('error', "Vous n'avez pas l'autorisation d'exporter les données de facturation.");
            header('Location: ' . View::url('facturation/filtre'));
            exit;
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $startMonth = isset($_GET['start_month']) ? (int) $_GET['start_month'] : $currentMonth;
        $startYear = isset($_GET['start_year']) ? (int) $_GET['start_year'] : $currentYear;
        $endMonth = isset($_GET['end_month']) ? (int) $_GET['end_month'] : $currentMonth;
        $endYear = isset($_GET['end_year']) ? (int) $_GET['end_year'] : $currentYear;

        [, $selectedAgenceId] = $this->resolveAgenceFilter();
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

        if (!Auth::isAdmin() && !Auth::isFacturationPrivileged() && !Auth::can(PermissionEntityRegistry::EXPORTER_FACTURATION_AVEC_MONTANT)) {
            Session::flash('error', "Vous n'avez pas l'autorisation d'exporter les données de facturation.");
            header('Location: ' . View::url('facturation/filtre'));
            exit;
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $startMonth = isset($_GET['start_month']) ? (int) $_GET['start_month'] : $currentMonth;
        $startYear = isset($_GET['start_year']) ? (int) $_GET['start_year'] : $currentYear;
        $endMonth = isset($_GET['end_month']) ? (int) $_GET['end_month'] : $currentMonth;
        $endYear = isset($_GET['end_year']) ? (int) $_GET['end_year'] : $currentYear;

        [, $selectedAgenceId] = $this->resolveAgenceFilter();
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

    /**
     * @return array{0: bool, 1: int} [$canSeeAllAgencies, $selectedAgenceId]
     */
    private function resolveAgenceFilter(): array
    {
        $userAgenceId = $_SESSION['user']['agence_id'] ?? null;
        $canSeeAllAgencies = Auth::isAdmin()
            || Auth::can(PermissionEntityRegistry::CONSULTER_TOUTES_FACTURES_TOUTES_AGENCES)
            || Auth::isFacturationPrivileged();

        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : ($canSeeAllAgencies ? 0 : ($userAgenceId ? (int) $userAgenceId : 0));
        if (!$canSeeAllAgencies && $userAgenceId) {
            $selectedAgenceId = (int) $userAgenceId; // Force agent agence
        }

        return [$canSeeAllAgencies, $selectedAgenceId];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>} [$whereClause, $params]
     */
    private function buildFilterConditions(string $startDate, string $endDate, int $agenceId, string $trajetCode): array
    {
        $conditions = ['f.date_emission >= :start_date', 'f.date_emission <= :end_date'];
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($agenceId > 0) {
            $conditions[] = 'f.agence_id = :agence_id';
            $params['agence_id'] = $agenceId;
        }

        if ($trajetCode !== '' && $trajetCode !== 'all') {
            $conditions[] = '(t.code = :trajet_code1 OR c.trajet = :trajet_code2)';
            $params['trajet_code1'] = $trajetCode;
            $params['trajet_code2'] = $trajetCode;
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * Statistiques agrégées sur l'ensemble de la période filtrée (indépendant de la pagination).
     *
     * @return array{total_count: int, total_montant: float, total_poids: float, total_colis: int}
     */
    private function aggregateFilteredFactures(string $startDate, string $endDate, int $agenceId, string $trajetCode): array
    {
        [$where, $params] = $this->buildFilterConditions($startDate, $endDate, $agenceId, $trajetCode);

        $sql = "
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(f.montant_total), 0) AS total_montant,
                COALESCE(SUM(c.poids_total), 0) AS total_poids,
                COALESCE(SUM(c.nombre_colis), 0) AS total_colis
            FROM lbp_factures f
            JOIN lbp_colis c ON f.colis_id = c.id
            LEFT JOIN trajets t ON COALESCE(f.trajet_id, c.trajet_id) = t.id
            WHERE {$where}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_count' => 0, 'total_montant' => 0.0, 'total_poids' => 0.0, 'total_colis' => 0];
    }

    private function fetchFilteredFactures(
        string $startDate,
        string $endDate,
        int $agenceId,
        string $trajetCode,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        [$where, $params] = $this->buildFilterConditions($startDate, $endDate, $agenceId, $trajetCode);

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
                COALESCE(fal.modifications_count, 0) AS modifications_count
            FROM lbp_factures f
            JOIN lbp_colis c ON f.colis_id = c.id
            JOIN lbp_clients cl ON f.client_id = cl.id
            JOIN company_sites s ON f.agence_id = s.id
            LEFT JOIN users u ON COALESCE(f.created_by, f.agent_id, f.caissiere_id) = u.id
            LEFT JOIN trajets t ON COALESCE(f.trajet_id, c.trajet_id) = t.id
            LEFT JOIN (
                SELECT facture_id, COUNT(*) AS modifications_count
                FROM factures_audit_log
                GROUP BY facture_id
            ) fal ON fal.facture_id = f.id
            WHERE {$where}
            ORDER BY f.date_emission DESC
        ";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();

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
