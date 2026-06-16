<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Security\PermissionEntityRegistry;
use App\Services\BusinessModuleService;

final class TrackingController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        AuthMiddleware::checkPermission(PermissionEntityRegistry::TRACKING_GPS, 'view');
        
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->trackingDashboard();
        
        $this->view('modules/dashboard', [
            'pageTitle' => 'Tableau de bord Tracking',
            'moduleName' => 'Tracking',
            'moduleCode' => 'TRK',
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => $module['navigation'],
            'dashboardModule' => $module,
            'additionalStyles' => ['css/finea-ui.css']
        ]);
    }
}
