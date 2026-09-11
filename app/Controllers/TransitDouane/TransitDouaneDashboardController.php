<?php

declare(strict_types=1);

namespace App\Controllers\TransitDouane;

use App\Controllers\BaseController;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use App\Repositories\TransitDouane\TransitDouaneDashboardRepository;
use App\Security\ModuleAccess;
use App\Services\TransitDouane\TransitDouaneDashboardService;

final class TransitDouaneDashboardController extends BaseController
{
    private const SLUG = 'transit-douane';

    private TransitDouaneDashboardService $service;

    public function __construct()
    {
        $this->service = new TransitDouaneDashboardService(
            new TransitDouaneDashboardRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        RoleMiddleware::check(ModuleAccess::rolesLecture(self::SLUG));

        $agenceId = ModuleAccess::agenceVisible();
        $module = $this->service->dashboard();

        $this->view('transit_douane/dashboard', [
            'pageTitle' => 'Transit Douane',
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => (array) $module['navigation'],
            'additionalStyles' => ['css/finea-ui.css'],
            'donnees' => $this->service->dossiers($agenceId),
            'agenceLabel' => $this->service->nomAgence($agenceId),
        ]);
    }
}
