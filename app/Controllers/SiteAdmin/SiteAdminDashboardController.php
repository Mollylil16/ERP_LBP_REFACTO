<?php

declare(strict_types=1);

namespace App\Controllers\SiteAdmin;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\SiteAdmin\SiteAdminDashboardRepository;
use App\Services\SiteAdmin\SiteAdminDashboardService;
use App\View\Pages\SiteAdmin\DashboardPage;

final class SiteAdminDashboardController extends SiteAdminBaseController
{
    private SiteAdminDashboardService $service;

    public function __construct()
    {
        $this->service = new SiteAdminDashboardService(new SiteAdminDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();

        $this->siteAdminView(
            'site_admin/dashboard',
            'Tableau de bord ' . (string) $module['label'],
            'dashboard',
            ['page' => new DashboardPage($module)],
            $module,
        );
    }
}
