<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Repositories\Finance\FinanceDashboardRepository;

final class FinanceDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    private FinanceDashboardRepository $financeRepository;

    public function __construct(FinanceDashboardRepository $repository)
    {
        parent::__construct($repository);
        $this->financeRepository = $repository;
    }

    /**
     * Get rich financial statistics
     */
    public function getFinanceStats(): array
    {
        return $this->financeRepository->getFinanceStats();
    }

    /**
     * Get recent invoices
     */
    public function getRecentFactures(int $limit = 5): array
    {
        return $this->financeRepository->getRecentFactures($limit);
    }

    /**
     * Get recent daily closures
     */
    public function getRecentEtats(int $limit = 5): array
    {
        return $this->financeRepository->getRecentEtats($limit);
    }

    /**
     * Get recent double-entry accounting entries
     */
    public function getRecentEcritures(int $limit = 5): array
    {
        return $this->financeRepository->getRecentEcritures($limit);
    }
}
