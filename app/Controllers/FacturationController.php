<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Security\PermissionEntityRegistry;
use App\Services\BusinessModuleService;

final class FacturationController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        AuthMiddleware::checkPermission(PermissionEntityRegistry::FACTURATION_FACTURES, 'view');
        
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->facturationDashboard();
        
        $this->view('modules/dashboard', [
            'pageTitle' => 'Tableau de bord Facturation',
            'moduleName' => 'Facturation',
            'moduleCode' => 'INV',
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => $module['navigation'],
            'dashboardModule' => $module,
            'additionalStyles' => ['css/finea-ui.css']
        ]);
    }
}
