<?php

declare(strict_types=1);

namespace App\Services\FlotteTransport;

use App\Repositories\FlotteTransport\FlotteTransportDashboardRepository;

final class FlotteTransportDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(FlotteTransportDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
