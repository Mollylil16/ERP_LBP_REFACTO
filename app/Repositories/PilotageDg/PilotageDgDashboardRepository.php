<?php

declare(strict_types=1);

namespace App\Repositories\PilotageDg;

final class PilotageDgDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('pilotage-dg');
    }
}
