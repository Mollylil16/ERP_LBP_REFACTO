<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EntrepotsDashboardRepository;

final class EntrepotsDashboardService extends AbstractModuleDashboardService
{
    public function __construct(EntrepotsDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
