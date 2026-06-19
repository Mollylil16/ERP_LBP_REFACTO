<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Admin\AdminDashboardRepository;
use App\Repositories\Admin\PermissionRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\Admin\AdminDashboardService;
use App\View\Pages\Admin\DashboardPage;

final class AdminDashboardController extends AdminBaseController
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
        $this->adminView('admin/dashboard', 'Tableau de bord', 'dashboard', [
            'page' => new DashboardPage($this->service->dashboard()),
        ]);
    }
}
