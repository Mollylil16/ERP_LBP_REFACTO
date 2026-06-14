<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\RhDashboardRepository;
use App\Services\RhDashboardService;
use App\Services\DataVisibilityService;

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
            'user' => [
                'id' => Auth::id(),
                'name' => Auth::user()?->fullName ?? 'Administrateur',
            ],
            'mode' => $mode,
            'dashboard' => $service->build(),
            'restrictedTables' => (new DataVisibilityService())->restrictedTables(),
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css'],
            'additionalScripts' => ['js/rh.js'],
        ]);
    }
}
