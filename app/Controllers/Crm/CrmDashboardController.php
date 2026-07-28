<?php

declare(strict_types=1);

namespace App\Controllers\Crm;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Crm\CrmDashboardRepository;
use App\Services\Crm\CrmDashboardService;

final class CrmDashboardController extends CrmBaseController
{
    private CrmDashboardService $service;

    public function __construct()
    {
        $this->service = new CrmDashboardService(new CrmDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();

        $page = new \App\View\Pages\Crm\DashboardPage($module);

        $this->crmView('crm/dashboard', 'Tableau de bord ' . (string) $module['label'], 'dashboard', $module, [
            'dashboardModule' => $module,
            'page' => $page,
        ]);
    }
}
