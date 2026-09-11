<?php

declare(strict_types=1);

namespace App\Controllers\PortefeuilleClients;

use App\Controllers\BaseController;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use App\Repositories\PortefeuilleClients\PortefeuilleClientsDashboardRepository;
use App\Security\ModuleAccess;
use App\Services\PortefeuilleClients\PortefeuilleClientsDashboardService;

final class PortefeuilleClientsDashboardController extends BaseController
{
    private const SLUG = 'portefeuille-clients';

    /** Profondeurs d'historique proposées, en mois. */
    private const PERIODES = [3, 6, 12, 24];

    private PortefeuilleClientsDashboardService $service;

    public function __construct()
    {
        $this->service = new PortefeuilleClientsDashboardService(
            new PortefeuilleClientsDashboardRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        RoleMiddleware::check(ModuleAccess::rolesLecture(self::SLUG));

        $agenceId = ModuleAccess::agenceVisible();
        $mois = (int) ($_GET['mois'] ?? 12);
        if (!in_array($mois, self::PERIODES, true)) {
            $mois = 12;
        }

        $module = $this->service->dashboard();

        $this->view('portefeuille_clients/dashboard', [
            'pageTitle' => 'Portefeuille Clients',
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => (array) $module['navigation'],
            'additionalStyles' => ['css/finea-ui.css'],
            'donnees' => $this->service->portefeuille($agenceId, $mois),
            'agenceLabel' => $this->service->nomAgence($agenceId),
        ]);
    }
}
