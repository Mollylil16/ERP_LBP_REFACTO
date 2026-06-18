<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use App\Repositories\Colisage\ColisageDashboardRepository;

final class ColisageDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(ColisageDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
