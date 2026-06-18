<?php

declare(strict_types=1);

namespace App\Repositories\PortefeuilleClients;

final class PortefeuilleClientsDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('portefeuille-clients');
    }
}
