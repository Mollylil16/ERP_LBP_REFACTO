<?php

declare(strict_types=1);

namespace App\Services\PortefeuilleClients;

use App\Repositories\PortefeuilleClients\PortefeuilleClientsDashboardRepository;

final class PortefeuilleClientsDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(PortefeuilleClientsDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
