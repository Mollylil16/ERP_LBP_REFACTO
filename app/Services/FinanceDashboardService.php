<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FinanceDashboardRepository;

final class FinanceDashboardService extends AbstractModuleDashboardService
{
    public function __construct(FinanceDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
