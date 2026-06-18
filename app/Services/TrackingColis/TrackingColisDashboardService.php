<?php

declare(strict_types=1);

namespace App\Services\TrackingColis;

use App\Repositories\TrackingColis\TrackingColisDashboardRepository;

final class TrackingColisDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(TrackingColisDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
