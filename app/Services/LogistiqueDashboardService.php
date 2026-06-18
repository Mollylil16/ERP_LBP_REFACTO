<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LogistiqueDashboardRepository;

final class LogistiqueDashboardService extends AbstractModuleDashboardService
{
    public function __construct(LogistiqueDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
