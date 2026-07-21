<?php

declare(strict_types=1);

namespace App\Controllers\Crm;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Logistique\RayonRepository;
use App\Repositories\Shared\ModuleDashboardRepository;
use App\View\Pages\Crm\CallCenterPage;
use PDO;

final class CrmCallCenterController extends CrmBaseController
{
    private RayonRepository $rayonRepository;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->rayonRepository = new RayonRepository($pdo);
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $query = trim((string) ($_GET['q'] ?? ''));
        $searchResult = null;

        if ($query !== '') {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT c.*,
                       r.code_rayon, r.nom_rayon,
                       dest.name as destinataire_nom, dest.phone as destinataire_phone
                FROM lbp_colis c
                LEFT JOIN logistique_rayons r ON c.rayon_id = r.id
                LEFT JOIN lbp_clients dest ON c.destinataire_id = dest.id
                WHERE c.numero_tracking = :q
                   OR dest.phone LIKE :q_like
                   OR dest.name LIKE :q_like
                ORDER BY c.id DESC
                LIMIT 1
            ");
            $stmt->execute([
                'q' => $query,
                'q_like' => '%' . $query . '%',
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $searchResult = $row;
            }
        }

        $rayonsOverview = $this->rayonRepository->getAllRayons();

        $dashboardRepo = new ModuleDashboardRepository(Database::getConnection());
        $module = $dashboardRepo->dashboardFor('crm');

        $page = new CallCenterPage($searchResult, $rayonsOverview, $query);

        $this->crmView(
            'crm/callcenter',
            'Call Center - Recherche Emplacement Rayon',
            'callcenter',
            $module,
            [
                'page' => $page,
                'dashboardModule' => $module,
            ]
        );
    }
}
