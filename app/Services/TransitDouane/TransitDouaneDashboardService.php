<?php

declare(strict_types=1);

namespace App\Services\TransitDouane;

use App\Repositories\TransitDouane\TransitDouaneDashboardRepository;

final class TransitDouaneDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(TransitDouaneDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
