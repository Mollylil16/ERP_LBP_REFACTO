<?php

declare(strict_types=1);

namespace App\Services\Facturation;

use App\Repositories\Facturation\FacturationDashboardRepository;

final class FacturationDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(FacturationDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
