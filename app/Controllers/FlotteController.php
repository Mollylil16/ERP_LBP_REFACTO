<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Security\PermissionEntityRegistry;
use App\Services\BusinessModuleService;

final class FlotteController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        AuthMiddleware::checkPermission(PermissionEntityRegistry::FLOTTE_LIVREURS, 'view');
        
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->flotteDashboard();
        
        $this->view('modules/dashboard', [
            'pageTitle' => 'Tableau de bord Flotte',
            'moduleName' => 'Flotte',
            'moduleCode' => 'TRP',
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => $module['navigation'],
            'dashboardModule' => $module,
            'additionalStyles' => ['css/finea-ui.css']
        ]);
    }
}
