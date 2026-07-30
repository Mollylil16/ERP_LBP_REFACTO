<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Colisage\ColisageDashboardRepository;
use App\Services\Colisage\ColisageDashboardService;

final class ColisageDashboardController extends ColisageBaseController
{
    private ?ColisageDashboardService $service = null;

    private function getService(): ColisageDashboardService
    {
        if ($this->service === null) {
            $this->service = new ColisageDashboardService(new ColisageDashboardRepository(Database::getConnection()));
        }
        return $this->service;
    }

    public function index(): void
    {
        AuthMiddleware::check();

        try {
            $module = $this->getService()->dashboard();
        } catch (\Throwable $e) {
            $module = [
                'label' => 'Colisage & Expéditions',
                'code' => 'COL',
                'slug' => 'colisage',
                'kpis' => [
                    ['label' => 'Colis réceptionnés', 'value' => '0', 'meta' => 'En attente de groupage', 'href' => 'colisage/parcels?statut=RÉCEPTIONNÉ'],
                    ['label' => 'Voyages en transit', 'value' => '0', 'meta' => 'Manifestes en cours', 'href' => 'colisage/groupage'],
                    ['label' => 'Colis arrivés', 'value' => '0', 'meta' => 'À retirer en agence', 'href' => 'colisage/parcels?statut=ARRIVÉ'],
                    ['label' => 'Total livrés', 'value' => '0', 'meta' => 'Remis aux destinataires', 'tone' => 'success', 'href' => 'colisage/parcels?statut=RETIRÉ'],
                ],
                'recentParcels' => [],
                'recentExpeditions' => [],
                'quickActions' => [],
            ];
        }

        $page = new \App\View\Pages\Colisage\DashboardPage($module);

        $this->colisageView('colisage/dashboard', 'Tableau de bord ' . (string) ($module['label'] ?? 'Colisage'), 'dashboard', [
            'dashboardModule' => $module,
            'page' => $page,
        ]);
    }
}
