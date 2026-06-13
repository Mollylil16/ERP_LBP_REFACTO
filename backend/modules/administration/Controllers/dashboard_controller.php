<?php

namespace App\Modules\Administration\Controllers;

use App\Controllers\BaseController;
use App\Modules\Administration\Services\DashboardService;
use App\Core\Response;

class DashboardController extends BaseController
{
    public function __construct(private DashboardService $service = new DashboardService()) {}

    public function getDashboardStats(): void
    {
        $this->checkPermission('dashboard.view');

        try {
            $stats = $this->service->getStats();
            Response::success(['stats' => $stats]);
        } catch (\Exception $exception) {
            Response::error('Erreur lors de la récupération des statistiques: ' . $exception->getMessage(), 500);
        }
    }

    public function getTrackingEmployes(): void
    {
        // On demande la permission de voir les utilisateurs (ou une permission tracking.view)
        $this->checkPermission('users.read');

        try {
            $tracking = $this->service->getTracking();
            Response::success(['tracking' => $tracking]);
        } catch (\Exception $exception) {
            Response::error('Erreur lors de la récupération du tracking: ' . $exception->getMessage(), 500);
        }
    }
}
