<?php

declare(strict_types=1);

namespace App\Repositories\Logistique;

final class LogistiqueDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('logistique');
    }
}
