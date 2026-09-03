<?php

declare(strict_types=1);

namespace App\Controllers\Facturation;

use App\Middleware\AuthMiddleware;
use App\Database\Database;
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

        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));

        $startMonth = isset($_GET['start_month']) ? (int) $_GET['start_month'] : $currentMonth;
        $startYear = isset($_GET['start_year']) ? (int) $_GET['start_year'] : $currentYear;
        $endMonth = isset($_GET['end_month']) ? (int) $_GET['end_month'] : $currentMonth;
        $endYear = isset($_GET['end_year']) ? (int) $_GET['end_year'] : $currentYear;

        $startMonth = max(1, min(12, $startMonth));
        $endMonth = max(1, min(12, $endMonth));

        [$canSeeAllAgencies, $selectedAgenceId] = $this->resolveAgenceFilter();

        $selectedCategorie = trim((string) ($_GET['categorie_code'] ?? 'all'));
        $selectedStatutPaiement = trim((string) ($_GET['statut_paiement'] ?? 'all'));
        $searchQuery = trim((string) ($_GET['q'] ?? ''));

        // Build date range
        if ($dateFrom !== '' && $dateTo !== '') {
            $startDate = $dateFrom . ' 00:00:00';
            $endDate = $dateTo . ' 23:59:59';
        } else {
            $startDate = sprintf('%04d-%02d-01 00:00:00', $startYear, $startMonth);
            $lastDayOfEndMonth = date('t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
            $endDate = sprintf('%04d-%02d-%02d 23:59:59', $endYear, $endMonth, (int)$lastDayOfEndMonth);
        }

        // Active agences
        $sitesStmt = $this->pdo->query("SELECT id, name, code FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        $sites = $sitesStmt ? $sitesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // Active trajets
        $trajetsStmt = $this->pdo->query("SELECT * FROM trajets WHERE actif = 1 ORDER BY code ASC");
        $trajets = $trajetsStmt ? $trajetsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // Aggregate statistics over the WHOLE filtered range
        $agg = $this->aggregateFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedCategorie, $selectedStatutPaiement, $searchQuery);
        $totalCount = (int) ($agg['total_count'] ?? 0);

        $perPage = 50;
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        $page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;

        $results = $this->fetchFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedCategorie, $selectedStatutPaiement, $searchQuery, $perPage, $offset);

        $this->renderView('facturation/filtre', [
            'pageTitle'              => 'Vue d\'ensemble & Recherche Facturation',
            'startMonth'             => $startMonth,
            'startYear'              => $startYear,
            'endMonth'               => $endMonth,
            'endYear'                => $endYear,
            'dateFrom'               => $dateFrom,
            'dateTo'                 => $dateTo,
            'selectedAgenceId'       => $selectedAgenceId,
            'selectedCategorie'      => $selectedCategorie,
            'selectedStatutPaiement' => $selectedStatutPaiement,
            'searchQuery'            => $searchQuery,
            'canSeeAllAgencies'      => $canSeeAllAgencies,
            'sites'                  => $sites,
            'trajets'                => $trajets,
            'results'                => $results,
            'kpis'                   => [
                'totalCount'      => $totalCount,
                'totalMontantXof' => (float) ($agg['total_montant'] ?? 0),
                'totalEncaisse'   => (float) ($agg['total_encaisse'] ?? 0),
                'totalImpaye'     => (float) ($agg['total_impaye'] ?? 0),
                'totalPoids'      => (float) ($agg['total_poids'] ?? 0),
                'totalColis'      => (int) ($agg['total_colis'] ?? 0),
            ],
            'pagination'             => [
                'currentPage'  => $page,
                'totalPages'   => $totalPages,
                'itemsPerPage' => $perPage,
                'totalItems'   => $totalCount,
            ],
        ]);
    }

    public function exportPdf(): void
    {
        AuthMiddleware::check();

        if (!Auth::isAdmin() && !Auth::isFacturationPrivileged() && !Auth::can(PermissionEntityRegistry::EXPORTER_FACTURATION_AVEC_MONTANT)) {
            Session::flash('error', "Vous n'avez pas l'autorisation d'exporter les données de facturation.");
            header('Location: ' . View::url('facturation/filtre'));
            return;
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));

        $startMonth = isset($_GET['start_month']) ? (int) $_GET['start_month'] : $currentMonth;
        $startYear = isset($_GET['start_year']) ? (int) $_GET['start_year'] : $currentYear;
        $endMonth = isset($_GET['end_month']) ? (int) $_GET['end_month'] : $currentMonth;
        $endYear = isset($_GET['end_year']) ? (int) $_GET['end_year'] : $currentYear;

        [$canSeeAllAgencies, $selectedAgenceId] = $this->resolveAgenceFilter();
        $selectedCategorie = trim((string) ($_GET['categorie_code'] ?? 'all'));
        $selectedStatutPaiement = trim((string) ($_GET['statut_paiement'] ?? 'all'));
        $searchQuery = trim((string) ($_GET['q'] ?? ''));

        if ($dateFrom !== '' && $dateTo !== '') {
            $startDate = $dateFrom . ' 00:00:00';
            $endDate = $dateTo . ' 23:59:59';
            $periodText = sprintf('%s au %s', date('d/m/Y', strtotime($dateFrom)), date('d/m/Y', strtotime($dateTo)));
        } else {
            $startDate = sprintf('%04d-%02d-01 00:00:00', $startYear, $startMonth);
            $lastDayOfEndMonth = date('t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
            $endDate = sprintf('%04d-%02d-%02d 23:59:59', $endYear, $endMonth, (int)$lastDayOfEndMonth);
            $periodText = sprintf('%02d/%04d au %02d/%04d', $startMonth, $startYear, $endMonth, $endYear);
        }

        $results = $this->fetchFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedCategorie, $selectedStatutPaiement, $searchQuery);
        $agg = $this->aggregateFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedCategorie, $selectedStatutPaiement, $searchQuery);

        $agenceText = 'Toutes les agences';
        if ($selectedAgenceId > 0) {
            $stmt = $this->pdo->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $selectedAgenceId]);
            $agenceText = (string) ($stmt->fetchColumn() ?: 'Agence #' . $selectedAgenceId);
        }

        $categorieText = $this->getCategorieLabel($selectedCategorie);
        $statutText = match($selectedStatutPaiement) {
            'impayes' => '🔴 Impayés (Reste > 0)',
            'partiellement_payee' => '🟡 Partiellement Payés',
            'payee' => '🟢 Payés en Totalité',
            default => '⚪ Tous les Statuts',
        };

        $kpis = [
            'totalCount'      => (int) ($agg['total_count'] ?? 0),
            'totalMontantXof' => (float) ($agg['total_montant'] ?? 0),
            'totalEncaisse'   => (float) ($agg['total_encaisse'] ?? 0),
            'totalImpaye'     => (float) ($agg['total_impaye'] ?? 0),
            'totalPoids'      => (float) ($agg['total_poids'] ?? 0),
            'totalColis'      => (int) ($agg['total_colis'] ?? 0),
        ];

        require BASE_PATH . '/views/facturation/rapport_filtre_pdf.php';
        exit;
    }

    public function exportExcel(): void
    {
        AuthMiddleware::check();

        if (!Auth::isAdmin() && !Auth::isFacturationPrivileged() && !Auth::can(PermissionEntityRegistry::EXPORTER_FACTURATION_AVEC_MONTANT)) {
            Session::flash('error', "Vous n'avez pas l'autorisation d'exporter les données de facturation.");
            header('Location: ' . View::url('facturation/filtre'));
            return;
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));

        $startMonth = isset($_GET['start_month']) ? (int) $_GET['start_month'] : $currentMonth;
        $startYear = isset($_GET['start_year']) ? (int) $_GET['start_year'] : $currentYear;
        $endMonth = isset($_GET['end_month']) ? (int) $_GET['end_month'] : $currentMonth;
        $endYear = isset($_GET['end_year']) ? (int) $_GET['end_year'] : $currentYear;

        [$canSeeAllAgencies, $selectedAgenceId] = $this->resolveAgenceFilter();
        $selectedCategorie = trim((string) ($_GET['categorie_code'] ?? 'all'));
        $selectedStatutPaiement = trim((string) ($_GET['statut_paiement'] ?? 'all'));
        $searchQuery = trim((string) ($_GET['q'] ?? ''));

        if ($dateFrom !== '' && $dateTo !== '') {
            $startDate = $dateFrom . ' 00:00:00';
            $endDate = $dateTo . ' 23:59:59';
            $periodText = sprintf('%s au %s', date('d/m/Y', strtotime($dateFrom)), date('d/m/Y', strtotime($dateTo)));
        } else {
            $startDate = sprintf('%04d-%02d-01 00:00:00', $startYear, $startMonth);
            $lastDayOfEndMonth = date('t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
            $endDate = sprintf('%04d-%02d-%02d 23:59:59', $endYear, $endMonth, (int)$lastDayOfEndMonth);
            $periodText = sprintf('%02d/%04d au %02d/%04d', $startMonth, $startYear, $endMonth, $endYear);
        }

        $results = $this->fetchFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedCategorie, $selectedStatutPaiement, $searchQuery);
        $agg = $this->aggregateFilteredFactures($startDate, $endDate, $selectedAgenceId, $selectedCategorie, $selectedStatutPaiement, $searchQuery);

        $agenceText = 'Toutes les agences';
        if ($selectedAgenceId > 0) {
            $stmt = $this->pdo->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $selectedAgenceId]);
            $agenceText = (string) ($stmt->fetchColumn() ?: 'Agence #' . $selectedAgenceId);
        }

        $filename = 'export_facturation_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $output = fopen('php://output', 'w');
        if ($output) {
            // BOM UTF-8 pour Excel direct
            fwrite($output, "\xEF\xBB\xBF");

            // En-tête métadonnées
            fputcsv($output, ['LA BELLE PORTE TRANSIT - RAPPORT DE FACTURATION & IMPAYES'], ';');
            fputcsv($output, ['Date d\'extraction', date('d/m/Y H:i:s')], ';');
            fputcsv($output, ['Période', $periodText], ';');
            fputcsv($output, ['Agence', $agenceText], ';');
            fputcsv($output, ['Catégorie / Trajet', $this->getCategorieLabel($selectedCategorie)], ';');
            fputcsv($output, ['Statut Paiement', $selectedStatutPaiement], ';');
            fputcsv($output, ['Nombre de Factures', (int) ($agg['total_count'] ?? 0)], ';');
            fputcsv($output, ['Total Facturé (FCFA)', number_format((float) ($agg['total_montant'] ?? 0), 2, '.', '')], ';');
            fputcsv($output, ['Total Encaissé (FCFA)', number_format((float) ($agg['total_encaisse'] ?? 0), 2, '.', '')], ';');
            fputcsv($output, ['TOTAL IMPAYES (FCFA)', number_format((float) ($agg['total_impaye'] ?? 0), 2, '.', '')], ';');
            fputcsv($output, [], ';'); // Ligne vide

            // En-tête colonnes
            fputcsv($output, [
                'N° Facture',
                'N° Tracking / Colis',
                'Catégorie Métier',
                'Code Trajet',
                'Libellé Trajet',
                'Client',
                'Téléphone Client',
                'Agence',
                'Date Émission',
                'Poids Total (kg)',
                'Nombre Colis',
                'Montant Total (FCFA)',
                'Montant Encaissé (FCFA)',
                'Reste Impayé (FCFA)',
                'Statut Facture'
            ], ';');

            foreach ($results as $r) {
                $code = strtoupper((string) ($r['trajet_code'] ?? $r['col_trajet'] ?? 'AUTRE'));
                $catGroup = 'Autres';
                if (in_array($code, ['LB-CI', 'LB-FR', 'S-FR', 'S-CI', 'LB-CA', 'F-SN']) || str_starts_with($code, 'GP-')) {
                    $catGroup = 'Groupage Cargo';
                } elseif (in_array($code, ['CA-CI', 'CA-FR']) || str_starts_with($code, 'CR-')) {
                    $catGroup = 'Colis Rapide';
                } elseif (str_contains($code, 'DHL') || str_starts_with((string)($r['numero_tracking'] ?? ''), 'DHL')) {
                    $catGroup = 'DHL / Express';
                }

                fputcsv($output, [
                    $r['numero_facture'],
                    $r['numero_tracking'] ?? '',
                    $catGroup,
                    $code,
                    $r['trajet_libelle'] ?? '',
                    $r['client_name'] ?? '',
                    $r['client_phone'] ?? '',
                    $r['agence_name'] ?? '',
                    !empty($r['date_emission']) ? date('d/m/Y H:i', strtotime((string)$r['date_emission'])) : '',
                    number_format((float) ($r['poids_total'] ?? 0), 2, '.', ''),
                    (int) ($r['nombre_colis'] ?? 1),
                    number_format((float) $r['montant_total'], 2, '.', ''),
                    number_format((float) $r['montant_encaisse'], 2, '.', ''),
                    number_format((float) ($r['montant_restant'] ?? 0), 2, '.', ''),
                    $r['facture_statut'] ?? '',
                ], ';');
            }

            // Total row
            fputcsv($output, [
                'TOTAL',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                number_format((float) ($agg['total_poids'] ?? 0), 2, '.', ''),
                (int) ($agg['total_colis'] ?? 0),
                number_format((float) ($agg['total_montant'] ?? 0), 2, '.', ''),
                number_format((float) ($agg['total_encaisse'] ?? 0), 2, '.', ''),
                number_format((float) ($agg['total_impaye'] ?? 0), 2, '.', ''),
                ''
            ], ';');

            fclose($output);
        }

        if (PHP_SAPI !== 'cli') {
            exit;
        }
    }

    private function resolveAgenceFilter(): array
    {
        $userAgenceId = $_SESSION['user']['agence_id'] ?? null;
        $canSeeAllAgencies = Auth::isAdmin()
            || Auth::can(PermissionEntityRegistry::CONSULTER_TOUTES_FACTURES_TOUTES_AGENCES)
            || Auth::isFacturationPrivileged();

        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : ($canSeeAllAgencies ? 0 : ($userAgenceId ? (int) $userAgenceId : 0));
        if (!$canSeeAllAgencies && $userAgenceId) {
            $selectedAgenceId = (int) $userAgenceId;
        }

        return [$canSeeAllAgencies, $selectedAgenceId];
    }

    private function buildFilterConditions(
        string $startDate,
        string $endDate,
        int $agenceId,
        string $categorieCode,
        string $statutPaiement = 'all',
        string $searchQuery = ''
    ): array {
        $conditions = ['f.date_emission >= :start_date', 'f.date_emission <= :end_date'];
        $params = [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ];

        if ($agenceId > 0) {
            $conditions[] = 'f.agence_id = :agence_id';
            $params['agence_id'] = $agenceId;
        }

        // Filtre Statut Paiement (Impayés vs Tous)
        if ($statutPaiement === 'impayes') {
            $conditions[] = "f.statut IN ('emise', 'partiellement_payee') AND f.montant_restant > 0";
        } elseif ($statutPaiement === 'partiellement_payee') {
            $conditions[] = "f.statut = 'partiellement_payee'";
        } elseif ($statutPaiement === 'payee') {
            $conditions[] = "f.statut = 'payee' OR f.montant_restant <= 0";
        }

        // Filtre Catégories & Codes
        if ($categorieCode !== '' && $categorieCode !== 'all') {
            if ($categorieCode === 'groupage_cargo') {
                $conditions[] = "(t.code IN ('LB-CI', 'LB-FR', 'S-FR', 'S-CI', 'LB-CA', 'F-SN') OR c.trajet IN ('LB-CI', 'LB-FR', 'S-FR', 'S-CI', 'LB-CA', 'F-SN') OR t.code LIKE 'GP-%' OR c.trajet LIKE 'GP-%')";
            } elseif ($categorieCode === 'colis_rapide') {
                $conditions[] = "(t.code IN ('CA-CI', 'CA-FR') OR c.trajet IN ('CA-CI', 'CA-FR') OR t.code LIKE 'CR-%' OR c.trajet LIKE 'CR-%')";
            } elseif ($categorieCode === 'dhl') {
                $conditions[] = "(t.code LIKE '%DHL%' OR c.trajet LIKE '%DHL%' OR c.numero_tracking LIKE 'DHL%')";
            } elseif ($categorieCode === 'autres') {
                $conditions[] = "(t.code NOT IN ('LB-CI', 'LB-FR', 'S-FR', 'S-CI', 'LB-CA', 'F-SN', 'CA-CI', 'CA-FR') AND (t.code NOT LIKE '%DHL%' OR t.code IS NULL))";
            } else {
                $conditions[] = '(t.code = :trajet_code1 OR c.trajet = :trajet_code2)';
                $params['trajet_code1'] = $categorieCode;
                $params['trajet_code2'] = $categorieCode;
            }
        }

        if ($searchQuery !== '') {
            $conditions[] = '(f.numero_facture LIKE :q1 OR c.numero_tracking LIKE :q2 OR cl.name LIKE :q3 OR cl.phone LIKE :q4)';
            $like = '%' . $searchQuery . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function aggregateFilteredFactures(
        string $startDate,
        string $endDate,
        int $agenceId,
        string $categorieCode,
        string $statutPaiement = 'all',
        string $searchQuery = ''
    ): array {
        [$where, $params] = $this->buildFilterConditions($startDate, $endDate, $agenceId, $categorieCode, $statutPaiement, $searchQuery);

        $sql = "
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(f.montant_total), 0) AS total_montant,
                COALESCE(SUM(f.montant_encaisse), 0) AS total_encaisse,
                COALESCE(SUM(f.montant_restant), 0) AS total_impaye,
                COALESCE(SUM(c.poids_total), 0) AS total_poids,
                COALESCE(SUM(c.nombre_colis), 0) AS total_colis
            FROM lbp_factures f
            JOIN lbp_colis c ON f.colis_id = c.id
            LEFT JOIN lbp_clients cl ON f.client_id = cl.id
            LEFT JOIN trajets t ON COALESCE(f.trajet_id, c.trajet_id) = t.id
            WHERE {$where}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_count'   => 0,
            'total_montant' => 0.0,
            'total_encaisse'=> 0.0,
            'total_impaye'  => 0.0,
            'total_poids'   => 0.0,
            'total_colis'   => 0
        ];
    }

    private function fetchFilteredFactures(
        string $startDate,
        string $endDate,
        int $agenceId,
        string $categorieCode,
        string $statutPaiement = 'all',
        string $searchQuery = '',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        [$where, $params] = $this->buildFilterConditions($startDate, $endDate, $agenceId, $categorieCode, $statutPaiement, $searchQuery);

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
                c.id AS colis_id,
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

    private function getCategorieLabel(string $code): string
    {
        return match($code) {
            'all'            => 'Toutes les catégories',
            'groupage_cargo' => '✈️ Tout le Groupage Cargo',
            'colis_rapide'   => '⚡ Tout le Colis Rapide',
            'dhl'            => '🚚 DHL / Express',
            'autres'         => 'Autres / Transit',
            'LB-CI'          => 'LB-CI : Abidjan ➔ France',
            'LB-FR'          => 'LB-FR : France ➔ Abidjan',
            'S-FR'           => 'S-FR : Sénégal ➔ France',
            'S-CI'           => 'S-CI : Sénégal ➔ Côte d\'Ivoire',
            'LB-CA'          => 'LB-CA : Abidjan ➔ Canada',
            'F-SN'           => 'F-SN : France ➔ Sénégal',
            'CA-CI'          => 'CA-CI : Abidjan ➔ Paris (Rapide)',
            'CA-FR'          => 'CA-FR : Paris ➔ Abidjan (Rapide)',
            default          => $code,
        };
    }

    private function renderView(string $view, array $data): void
    {
        $dashService = new \App\Services\Shared\ModuleDashboardService();
        $module = $dashService->dashboard('facturation');

        $layoutData = [
            'pageTitle'        => $data['pageTitle'] ?? 'Facturation',
            'moduleName'       => 'Facturation',
            'moduleCode'       => 'FAC',
            'activeModule'     => 'filtre',
            'moduleNavigation' => $module['items'] ?? [],
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
