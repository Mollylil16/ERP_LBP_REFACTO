<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FacturationDashboardRepository;

final class FacturationDashboardService extends AbstractModuleDashboardService
{
    public function __construct(FacturationDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
