<?php

declare(strict_types=1);

namespace App\Controllers\Entrepots;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use App\Repositories\Entrepots\EntrepotsDashboardRepository;
use App\Security\ModuleAccess;
use App\Services\Entrepots\EntrepotsDashboardService;

final class EntrepotsDashboardController extends BaseController
{
    private const SLUG = 'entrepots';

    private EntrepotsDashboardService $service;

    public function __construct()
    {
        $this->service = new EntrepotsDashboardService(new EntrepotsDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        RoleMiddleware::check(ModuleAccess::rolesLecture(self::SLUG));

        $agenceId = ModuleAccess::agenceVisible();
        $module = $this->service->dashboard();
        $donnees = $this->service->occupation($agenceId);

        $this->view('entrepots/dashboard', [
            'pageTitle' => 'Entrepôts',
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => (array) $module['navigation'],
            'additionalStyles' => ['css/finea-ui.css'],
            'donnees' => $donnees,
            'agenceLabel' => $this->service->nomAgence($agenceId),
        ]);
    }
}
