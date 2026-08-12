<?php

declare(strict_types=1);

namespace App\Controllers\PilotageDg;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use App\Repositories\PilotageDg\PilotageDgDashboardRepository;
use App\Services\PilotageDg\PilotageDgDashboardService;

final class PilotageDgDashboardController extends BaseController
{
    private PilotageDgDashboardService $service;

    public function __construct()
    {
        $this->service = new PilotageDgDashboardService(new PilotageDgDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->dashboard();

        $this->view('pilotage_dg/dashboard', $this->viewData($module) + [
            'dashboardModule' => $module,
        ]);
    }

    public function personnel(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->moduleMeta();
        $supervision = $this->service->personnelSupervision();

        $this->view('pilotage_dg/personnel', $this->viewData($module, 'personnel') + [
            'employees' => $supervision['employees'],
            'topHonnetes' => $supervision['topHonnetes'] ?? [],
            'alerts' => $supervision['alerts'],
        ]);
    }

    public function validations(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->moduleMeta();
        $pending = $this->service->pendingValidations();

        $this->view('pilotage_dg/validations', $this->viewData($module, 'validations') + [
            'workflows' => $pending['workflows'],
            'legalRequests' => $pending['legalRequests'],
            'paymentRequests' => $pending['paymentRequests'],
        ]);
    }

    public function anomalies(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->moduleMeta();
        $anomalies = $this->service->anomalies();

        $this->view('pilotage_dg/anomalies', $this->viewData($module, 'anomalies') + [
            'signalements' => $anomalies['signalements'] ?? [],
            'ecartsCaisse' => $anomalies['ecartsCaisse'],
            'agentsSuspects' => $anomalies['agentsSuspects'],
            'agencesImpayes' => $anomalies['agencesImpayes'],
            'colisSuspects' => $anomalies['colisSuspects'] ?? [],
            'rapprochementIndependant' => $anomalies['rapprochementIndependant'] ?? [],
        ]);
    }

    public function audit(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->moduleMeta();
        $filters = [
            'entity_type' => $_GET['entity_type'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'start_date' => $_GET['start_date'] ?? '',
            'end_date' => $_GET['end_date'] ?? '',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->service->auditLog($filters, $page);

        $this->view('pilotage_dg/audit', $this->viewData($module, 'audit') + [
            'logs' => $result['logs'],
            'entityTypes' => $result['entityTypes'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    /**
     * @param array<string,mixed> $module
     * @return array<string,mixed>
     */
    private function viewData(array $module, string $activeKey = 'dashboard'): array
    {
        return [
            'pageTitle' => 'Tableau de bord ' . (string) $module['label'],
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => $activeKey,
            'moduleNavigation' => (array) $module['navigation'],
            'additionalStyles' => ['css/finea-ui.css'],
        ];
    }
}
