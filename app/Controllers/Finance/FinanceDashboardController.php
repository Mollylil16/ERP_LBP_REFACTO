<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Finance\FinanceDashboardRepository;
use App\Services\Finance\FinanceDashboardService;

final class FinanceDashboardController extends FinanceBaseController
{
    private FinanceDashboardService $service;

    public function __construct()
    {
        $this->service = new FinanceDashboardService(new FinanceDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();

        // Fetch live finance stats and recent records
        $stats = $this->service->getFinanceStats();
        $recentFactures = $this->service->getRecentFactures(5);
        $recentEtats = $this->service->getRecentEtats(5);
        $recentEcritures = $this->service->getRecentEcritures(5);

        $page = new \App\View\Pages\Finance\DashboardPage(
            $stats,
            $recentFactures,
            $recentEcritures,
            $recentEtats
        );

        $this->financeView('finance/dashboard', 'Tableau de bord ' . (string) $module['label'], 'dashboard', [
            'dashboardModule' => $module,
            'page' => $page,
        ]);
    }
}
