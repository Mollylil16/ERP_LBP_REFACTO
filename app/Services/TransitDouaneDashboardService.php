<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TransitDouaneDashboardRepository;

final class TransitDouaneDashboardService extends AbstractModuleDashboardService
{
    public function __construct(TransitDouaneDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
