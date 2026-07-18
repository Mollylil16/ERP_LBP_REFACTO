<?php

declare(strict_types=1);

namespace App\Controllers\Logistique;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Logistique\LogistiqueDashboardRepository;
use App\Services\Logistique\LogistiqueDashboardService;

final class LogistiqueDashboardController extends LogistiqueBaseController
{
    private LogistiqueDashboardService $service;

    public function __construct()
    {
        $this->service = new LogistiqueDashboardService(new LogistiqueDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();

        $page = new \App\View\Pages\Logistique\DashboardPage($module);

        $this->logistiqueView('logistique/dashboard', 'Tableau de bord ' . (string) $module['label'], 'dashboard', $module, [
            'dashboardModule' => $module,
            'page' => $page,
        ]);
    }
}
