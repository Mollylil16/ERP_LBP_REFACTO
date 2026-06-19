<?php

namespace App\Controllers\Rh;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhDashboardRepository;
use App\Services\Rh\RhDashboardService;
use App\Services\Support\DataVisibilityService;
use App\View\Pages\Rh\DashboardPage;

class RhDashboardController extends RhBaseController
{
    public function index(): void
    {
        AuthMiddleware::check();

        $mode = strtolower(trim((string) ($_GET['view'] ?? 'classic')));
        if (!in_array($mode, ['classic', 'statistique', 'analytique'], true)) {
            $mode = 'classic';
        }

        $service = new RhDashboardService(
            new RhDashboardRepository(Database::getConnection())
        );

        $this->rhView('rh/dashboard', 'Tableau de bord RH', 'dashboard', [
            'page' => new DashboardPage(
                $service->build(),
                $mode,
                (new DataVisibilityService())->restrictedTables(),
            ),
        ]);
    }
}
