<?php

declare(strict_types=1);

namespace App\Services\Logistique;

use App\Repositories\Logistique\LogistiqueDashboardRepository;

final class LogistiqueDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(LogistiqueDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
