<?php

declare(strict_types=1);

namespace App\Controllers\Logistique;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Logistique\LogistiqueDashboardRepository;
use App\Repositories\Logistique\RayonRepository;
use App\Services\Logistique\LogistiqueDashboardService;

final class LogistiqueDashboardController extends LogistiqueBaseController
{
    private LogistiqueDashboardService $service;
    private RayonRepository $rayonRepository;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->service = new LogistiqueDashboardService(new LogistiqueDashboardRepository($pdo));
        $this->rayonRepository = new RayonRepository($pdo);
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();
        $page = new \App\View\Pages\Logistique\DashboardPage($module);
        $rayons = $this->rayonRepository->getAllRayons();

        $this->logistiqueView('logistique/dashboard', 'Tableau de bord ' . (string) $module['label'], 'dashboard', $module, [
            'dashboardModule' => $module,
            'page' => $page,
            'rayons' => $rayons,
        ]);
    }
}
