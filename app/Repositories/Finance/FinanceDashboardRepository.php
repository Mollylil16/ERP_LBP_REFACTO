<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

final class FinanceDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('finance');
    }
}
