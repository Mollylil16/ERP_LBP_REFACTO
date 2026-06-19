<?php

namespace App\Controllers\Rh;

use App\Controllers\BaseController;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhDashboardRepository;
use App\Services\Rh\RhDashboardService;
use App\Services\Support\DataVisibilityService;
use App\View\Navigation\RhNavigation;
use App\View\Pages\Rh\DashboardPage;

class RhDashboardController extends BaseController
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

        $this->view('rh/dashboard', [
            'pageTitle' => 'Tableau de bord RH',
            'moduleName' => 'Ressources humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'dashboard',
            'page' => new DashboardPage(
                $service->build(),
                $mode,
                (new DataVisibilityService())->restrictedTables(),
            ),
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css'],
            'additionalScripts' => ['js/rh.js'],
            'moduleNavigation' => RhNavigation::items(),
        ]);
    }
}
