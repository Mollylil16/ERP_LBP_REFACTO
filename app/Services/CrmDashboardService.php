<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CrmDashboardRepository;

final class CrmDashboardService extends AbstractModuleDashboardService
{
    public function __construct(CrmDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
