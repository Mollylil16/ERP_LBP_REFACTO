<?php

declare(strict_types=1);

namespace App\Controllers\FlotteTransport;

use App\Controllers\BaseController;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use App\Repositories\FlotteTransport\FlotteTransportDashboardRepository;
use App\Security\ModuleAccess;
use App\Services\FlotteTransport\FlotteTransportDashboardService;

final class FlotteTransportDashboardController extends BaseController
{
    private const SLUG = 'flotte-transport';

    private FlotteTransportDashboardService $service;

    public function __construct()
    {
        $this->service = new FlotteTransportDashboardService(
            new FlotteTransportDashboardRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        RoleMiddleware::check(ModuleAccess::rolesLecture(self::SLUG));

        $agenceId = ModuleAccess::agenceVisible();
        $module = $this->service->dashboard();

        $this->view('flotte_transport/dashboard', [
            'pageTitle' => 'Flotte / Transport',
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => (array) $module['navigation'],
            'additionalStyles' => ['css/finea-ui.css'],
            'donnees' => $this->service->flotte($agenceId),
            'agenceLabel' => $this->service->nomAgence($agenceId),
        ]);
    }
}
