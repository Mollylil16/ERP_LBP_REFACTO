<?php

declare(strict_types=1);

namespace App\Repositories\TrackingColis;

final class TrackingColisDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('tracking-colis');
    }
}
