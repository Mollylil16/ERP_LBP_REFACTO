<?php

declare(strict_types=1);

namespace App\Repositories;

final class ColisageDashboardRepository extends ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('colisage');
    }
}
