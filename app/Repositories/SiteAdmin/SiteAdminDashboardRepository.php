<?php

declare(strict_types=1);

namespace App\Repositories\SiteAdmin;

final class SiteAdminDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('site-admin');
    }
}
