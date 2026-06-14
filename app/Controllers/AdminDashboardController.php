<?php

namespace App\Controllers;

use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\PermissionRepository;
use App\Repositories\RhPersonnelRepository;
use App\Repositories\UserRepository;
use App\Services\AdminService;

class AdminDashboardController extends BaseController
{
    private AdminService $service;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->service = new AdminService(
            new UserRepository($pdo),
            new PermissionRepository($pdo),
            new RhPersonnelRepository($pdo),
            $pdo
        );
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
