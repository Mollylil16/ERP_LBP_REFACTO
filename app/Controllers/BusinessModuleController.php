<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\ModuleDashboardService;

class BusinessModuleController extends BaseController
{
    private ModuleDashboardService $service;

    /** @var array<string,string> */
    private array $views = [
        'finance' => 'finance/dashboard',
        'colisage' => 'colisage/dashboard',
        'logistique' => 'logistique/dashboard',
        'crm' => 'crm/dashboard',
        'tickets' => 'tickets/dashboard',
        'site-admin' => 'site_admin/dashboard',
        'transit-douane' => 'transit_douane/dashboard',
        'tracking-colis' => 'tracking_colis/dashboard',
        'facturation' => 'facturation/dashboard',
        'entrepots' => 'entrepots/dashboard',
        'flotte-transport' => 'flotte_transport/dashboard',
        'portefeuille-clients' => 'portefeuille_clients/dashboard',
        'agents-correspondants' => 'agents_correspondants/dashboard',
        'pilotage-dg' => 'pilotage_dg/dashboard',
    ];

    public function __construct()
    {
        $this->service = new ModuleDashboardService();
    }

    public function dashboard(string $slug): void
    {
        AuthMiddleware::check();
        $module = $this->service->dashboard($slug);
        $view = $this->views[$slug] ?? throw new \InvalidArgumentException('Vue dédiée manquante pour le module: ' . $slug);

        $this->view($view, [
            'pageTitle' => 'Tableau de bord ' . (string) $module['label'],
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => (array) $module['navigation'],
            'dashboardModule' => $module,
            'additionalStyles' => ['css/finea-ui.css'],
        ]);
    }

    public function finance(): void { $this->dashboard('finance'); }
    public function colisage(): void { $this->dashboard('colisage'); }
    public function logistique(): void { $this->dashboard('logistique'); }
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
