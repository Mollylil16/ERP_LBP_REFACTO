<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SiteAdminDashboardRepository;

final class SiteAdminDashboardService extends AbstractModuleDashboardService
{
    public function __construct(SiteAdminDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
