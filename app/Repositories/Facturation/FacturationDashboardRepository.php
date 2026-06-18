<?php

declare(strict_types=1);

namespace App\Repositories\Facturation;

final class FacturationDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('facturation');
    }
}
