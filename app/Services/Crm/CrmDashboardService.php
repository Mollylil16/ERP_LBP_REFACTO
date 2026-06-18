<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Repositories\Crm\CrmDashboardRepository;

final class CrmDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(CrmDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
