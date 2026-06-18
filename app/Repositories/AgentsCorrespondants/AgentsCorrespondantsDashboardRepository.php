<?php

declare(strict_types=1);

namespace App\Repositories\AgentsCorrespondants;

final class AgentsCorrespondantsDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('agents-correspondants');
    }
}
