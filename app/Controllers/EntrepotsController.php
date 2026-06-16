<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Security\PermissionEntityRegistry;
use App\Services\BusinessModuleService;

final class EntrepotsController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        AuthMiddleware::checkPermission(PermissionEntityRegistry::ENTREPOT_INVENTAIRES, 'view');
        
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->entrepotsDashboard();
        
        $this->view('modules/dashboard', [
            'pageTitle' => 'Tableau de bord Entrepôts',
            'moduleName' => 'Entrepôts',
            'moduleCode' => 'WHS',
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => $module['navigation'],
            'dashboardModule' => $module,
            'additionalStyles' => ['css/finea-ui.css']
        ]);
    }
}
