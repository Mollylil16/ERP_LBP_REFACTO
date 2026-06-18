<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Repositories\Finance\FinanceDashboardRepository;

final class FinanceDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(FinanceDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
