<?php

declare(strict_types=1);

namespace App\Services\AgentsCorrespondants;

use App\Repositories\AgentsCorrespondants\AgentsCorrespondantsDashboardRepository;

final class AgentsCorrespondantsDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(AgentsCorrespondantsDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
