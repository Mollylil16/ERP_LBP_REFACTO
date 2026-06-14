<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Services\BusinessModuleService;

final class WebsiteController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->websiteDashboard();
        $this->view('modules/dashboard', ['pageTitle'=>'Pilotage site internet','moduleName'=>'Site internet','moduleCode'=>'WEB','moduleTheme'=>$module,'activeModule'=>'dashboard','moduleNavigation'=>$module['navigation'],'dashboardModule'=>$module,'additionalStyles'=>['css/finea-ui.css']]);
    }

    public function publicSite(): void
    {
        $this->view('site/index', ['pageTitle'=>'Entreprise de transit import-export']);
    }
}
