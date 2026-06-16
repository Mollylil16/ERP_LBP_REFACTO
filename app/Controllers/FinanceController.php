<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Security\PermissionEntityRegistry;
use App\Services\BusinessModuleService;

final class FinanceController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        AuthMiddleware::checkPermission(PermissionEntityRegistry::FINANCE_RETRAITS, 'view');
        
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->financeDashboard();
        
        $this->view('modules/dashboard', [
            'pageTitle' => 'Tableau de bord Finance',
            'moduleName' => 'Finance',
            'moduleCode' => 'FIN',
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => $module['navigation'],
            'dashboardModule' => $module,
            'additionalStyles' => ['css/finea-ui.css']
        ]);
    }
}
