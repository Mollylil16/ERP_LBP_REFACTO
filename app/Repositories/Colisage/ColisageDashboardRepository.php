<?php

declare(strict_types=1);

namespace App\Repositories\Colisage;

final class ColisageDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('colisage');
    }
}
