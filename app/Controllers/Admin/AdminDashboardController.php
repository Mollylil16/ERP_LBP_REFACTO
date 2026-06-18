<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Admin\PermissionRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Admin\AdminDashboardRepository;
use App\Services\Admin\AdminDashboardService;

class AdminDashboardController extends BaseController
{
    private AdminDashboardService $service;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->service = new AdminDashboardService(new AdminDashboardRepository(
            new UserRepository($pdo),
            new PermissionRepository($pdo),
        ));
    }

    public function index(): void
    {
        AdminMiddleware::check();
        $this->view('admin/dashboard', $this->viewData('Tableau de bord', 'dashboard') + $this->service->dashboard());
    }

    private function viewData(string $pageTitle, string $activeModule): array
    {
        return [
            'pageTitle' => $pageTitle,
            'moduleName' => 'Administration',
            'moduleCode' => 'ADM',
            'activeModule' => $activeModule,
            'additionalStyles' => ['css/finea-ui.css', 'css/admin.css'],
            'additionalScripts' => ['js/admin.js'],
        ];
    }
}
