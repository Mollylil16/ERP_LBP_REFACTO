<?php

declare(strict_types=1);

namespace App\Services\SiteAdmin;

use App\Repositories\SiteAdmin\SiteAdminDashboardRepository;

final class SiteAdminDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(SiteAdminDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
