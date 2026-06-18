<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PilotageDgDashboardRepository;

final class PilotageDgDashboardService extends AbstractModuleDashboardService
{
    public function __construct(PilotageDgDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
