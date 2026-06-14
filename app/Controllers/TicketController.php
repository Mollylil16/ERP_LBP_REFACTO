<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Services\BusinessModuleService;

final class TicketController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->ticketDashboard();
        $this->view('modules/dashboard', ['pageTitle'=>'Tableau de bord Tickets','moduleName'=>'Tickets','moduleCode'=>'TIC','moduleTheme'=>$module,'activeModule'=>'dashboard','moduleNavigation'=>$module['navigation'],'dashboardModule'=>$module,'additionalStyles'=>['css/finea-ui.css']]);
    }
}
