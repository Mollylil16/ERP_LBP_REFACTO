<?php

namespace App\Controllers\Shared;

use App\Controllers\BaseController;

use App\Middleware\AuthMiddleware;
use App\Services\Shared\ModuleDashboardService;

class ModuleDashboardController extends BaseController
{
    private ModuleDashboardService $service;

    public function __construct()
    {
        $this->service = new ModuleDashboardService();
    }

    public function finance(): void
    {
        $this->render('finance');
    }

    public function colisage(): void
    {
        $this->render('colisage');
    }

    public function logistique(): void
    {
        $this->render('logistique');
    }

    public function employee(): void
    {
        $this->render('employee');
    }

    private function render(string $slug): void
    {
        AuthMiddleware::check();
        $module = $this->service->dashboard($slug);

        $this->view('modules/dashboard', [
            'pageTitle' => 'Tableau de bord ' . $module['label'],
            'moduleName' => $module['label'],
            'moduleCode' => $module['code'],
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => $module['navigation'],
            'dashboardModule' => $module,
            'additionalStyles' => ['css/finea-ui.css'],
        ]);
    }
}
