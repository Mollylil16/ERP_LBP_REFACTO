<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FlotteTransportDashboardRepository;

final class FlotteTransportDashboardService extends AbstractModuleDashboardService
{
    public function __construct(FlotteTransportDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
