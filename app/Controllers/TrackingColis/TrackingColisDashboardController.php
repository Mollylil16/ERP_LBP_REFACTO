<?php

declare(strict_types=1);

namespace App\Controllers\TrackingColis;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\TrackingColis\TrackingColisDashboardRepository;
use App\Services\TrackingColis\TrackingColisDashboardService;

final class TrackingColisDashboardController extends BaseController
{
    private TrackingColisDashboardService $service;

    public function __construct()
    {
        $this->service = new TrackingColisDashboardService(new TrackingColisDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();

        $this->view('tracking_colis/dashboard', $this->viewData($module) + [
            'dashboardModule' => $module,
        ]);
    }

    /**
     * @param array<string,mixed> $module
     * @return array<string,mixed>
     */
    private function viewData(array $module): array
    {
        return [
            'pageTitle' => 'Tableau de bord ' . (string) $module['label'],
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => (array) $module['navigation'],
            'additionalStyles' => ['css/finea-ui.css'],
        ];
    }
}
