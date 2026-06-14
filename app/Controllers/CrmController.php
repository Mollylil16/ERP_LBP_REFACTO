<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Services\BusinessModuleService;

final class CrmController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->crmDashboard();
        $this->view('modules/dashboard', ['pageTitle'=>'Tableau de bord CRM','moduleName'=>'CRM','moduleCode'=>'CRM','moduleTheme'=>$module,'activeModule'=>'dashboard','moduleNavigation'=>$module['navigation'],'dashboardModule'=>$module,'additionalStyles'=>['css/finea-ui.css']]);
    }
}
