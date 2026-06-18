<?php

declare(strict_types=1);

namespace App\Services\Entrepots;

use App\Repositories\Entrepots\EntrepotsDashboardRepository;

final class EntrepotsDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(EntrepotsDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
