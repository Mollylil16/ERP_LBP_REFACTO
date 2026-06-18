<?php

declare(strict_types=1);

namespace App\Repositories;

final class FinanceDashboardRepository extends ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('finance');
    }
}
