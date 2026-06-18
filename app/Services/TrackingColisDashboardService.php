<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TrackingColisDashboardRepository;

final class TrackingColisDashboardService extends AbstractModuleDashboardService
{
    public function __construct(TrackingColisDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
