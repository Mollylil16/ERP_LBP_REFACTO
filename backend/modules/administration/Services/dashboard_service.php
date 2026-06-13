<?php

namespace App\Modules\Administration\Services;

use App\Modules\Administration\Repositories\DashboardRepository;

class DashboardService
{
    public function __construct(private DashboardRepository $repository = new DashboardRepository()) {}

    public function getStats(): array
    {
        return $this->repository->getGlobalStats();
    }

    public function getTracking(): array
    {
        return $this->repository->fetchTrackingLogs();
    }
}
