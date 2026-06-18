<?php

declare(strict_types=1);

namespace App\Services\PilotageDg;

use App\Repositories\PilotageDg\PilotageDgDashboardRepository;

final class PilotageDgDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(PilotageDgDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
