<?php

declare(strict_types=1);

namespace App\Controllers\Facturation;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Facturation\FacturationDashboardRepository;
use App\Services\Facturation\FacturationDashboardService;

final class FacturationDashboardController extends FacturationBaseController
{
    private FacturationDashboardService $service;

    public function __construct()
    {
        $this->service = new FacturationDashboardService(new FacturationDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();

        $page = new \App\View\Pages\Facturation\DashboardPage($module);

        $this->facturationView('facturation/dashboard', 'Tableau de bord ' . (string) $module['label'], 'dashboard', $module, [
            'dashboardModule' => $module,
            'page' => $page,
        ]);
    }
}
