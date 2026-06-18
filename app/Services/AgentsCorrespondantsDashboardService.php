<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AgentsCorrespondantsDashboardRepository;

final class AgentsCorrespondantsDashboardService extends AbstractModuleDashboardService
{
    public function __construct(AgentsCorrespondantsDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
