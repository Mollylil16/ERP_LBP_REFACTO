<?php

declare(strict_types=1);

namespace App\Repositories\TransitDouane;

final class TransitDouaneDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('transit-douane');
    }
}
