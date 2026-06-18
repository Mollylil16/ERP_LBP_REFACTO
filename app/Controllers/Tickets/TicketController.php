<?php

namespace App\Controllers\Tickets;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Shared\BusinessModuleRepository;
use App\Services\Shared\BusinessModuleService;

final class TicketController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->ticketDashboard();
        $this->view('modules/dashboard', ['pageTitle'=>'Tableau de bord Tickets','moduleName'=>'Tickets','moduleCode'=>'TIC','moduleTheme'=>$module,'activeModule'=>'dashboard','moduleNavigation'=>$module['navigation'],'dashboardModule'=>$module,'additionalStyles'=>['css/finea-ui.css']]);
    }
}
