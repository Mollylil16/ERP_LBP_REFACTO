<?php

namespace App\Controllers\Shared;

use App\Controllers\BaseController;

use App\Middleware\AuthMiddleware;
use App\Services\Shared\ModuleDashboardService;

final class BusinessModuleController extends BaseController
{
    private ModuleDashboardService $service;

    public function __construct()
    {
        $this->service = new ModuleDashboardService();
    }

    public function dashboard(string $slug): void
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

    public function finance(): void { $this->dashboard('finance'); }
    public function colisage(): void { $this->dashboard('colisage'); }
    public function logistique(): void { $this->dashboard('logistique'); }
    public function employee(): void { $this->dashboard('employee'); }
    public function crm(): void { $this->dashboard('crm'); }
    public function tickets(): void { $this->dashboard('tickets'); }
    public function website(): void { $this->dashboard('site-admin'); }
    public function customs(): void { $this->dashboard('transit-douane'); }
    public function tracking(): void { $this->dashboard('tracking-colis'); }
    public function billing(): void { $this->dashboard('facturation'); }
    public function warehouses(): void { $this->dashboard('entrepots'); }
    public function fleet(): void { $this->dashboard('flotte-transport'); }
    public function clientPortfolio(): void { $this->dashboard('portefeuille-clients'); }
    public function agents(): void { $this->dashboard('agents-correspondants'); }
    public function executiveCenter(): void { $this->dashboard('pilotage-dg'); }
}
