<?php

declare(strict_types=1);

namespace App\Controllers\AgentsCorrespondants;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\AgentsCorrespondants\AgentsCorrespondantsDashboardRepository;
use App\Services\AgentsCorrespondants\AgentsCorrespondantsDashboardService;

final class AgentsCorrespondantsDashboardController extends BaseController
{
    private AgentsCorrespondantsDashboardService $service;

    public function __construct()
    {
        $this->service = new AgentsCorrespondantsDashboardService(new AgentsCorrespondantsDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();

        $this->view('agents_correspondants/dashboard', $this->viewData($module) + [
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
