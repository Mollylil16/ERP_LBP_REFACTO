<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PortefeuilleClientsDashboardRepository;

final class PortefeuilleClientsDashboardService extends AbstractModuleDashboardService
{
    public function __construct(PortefeuilleClientsDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
