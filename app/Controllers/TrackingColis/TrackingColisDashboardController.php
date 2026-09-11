<?php

declare(strict_types=1);

namespace App\Controllers\TrackingColis;

use App\Controllers\BaseController;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use App\Repositories\TrackingColis\TrackingColisDashboardRepository;
use App\Security\ModuleAccess;
use App\Services\TrackingColis\TrackingColisDashboardService;

final class TrackingColisDashboardController extends BaseController
{
    private const SLUG = 'tracking-colis';

    private TrackingColisDashboardService $service;

    public function __construct()
    {
        $this->service = new TrackingColisDashboardService(
            new TrackingColisDashboardRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        RoleMiddleware::check(ModuleAccess::rolesLecture(self::SLUG));

        $agenceId = ModuleAccess::agenceVisible();
        $recherche = trim((string) ($_GET['q'] ?? ''));
        $module = $this->service->dashboard();

        $this->view('tracking_colis/dashboard', [
            'pageTitle' => 'Tracking Colis',
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => (array) $module['navigation'],
            'additionalStyles' => ['css/finea-ui.css'],
            'donnees' => $this->service->suivi($agenceId, $recherche),
            'agenceLabel' => $this->service->nomAgence($agenceId),
        ]);
    }
}
