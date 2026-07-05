<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Colisage\ColisageDashboardRepository;
use App\Services\Colisage\ColisageDashboardService;

final class ColisageDashboardController extends ColisageBaseController
{
    private ColisageDashboardService $service;

    public function __construct()
    {
        $this->service = new ColisageDashboardService(new ColisageDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();

        $this->colisageView('colisage/dashboard', 'Tableau de bord ' . (string) $module['label'], 'dashboard', [
            'dashboardModule' => $module,
        ]);
    }
}
