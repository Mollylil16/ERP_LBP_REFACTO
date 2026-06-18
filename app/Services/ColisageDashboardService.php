<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ColisageDashboardRepository;

final class ColisageDashboardService extends AbstractModuleDashboardService
{
    public function __construct(ColisageDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
