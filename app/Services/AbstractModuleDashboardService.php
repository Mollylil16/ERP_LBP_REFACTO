<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ModuleDashboardRepository;

abstract class AbstractModuleDashboardService implements ModuleDashboardContract
{
    public function __construct(protected ModuleDashboardRepository $repository) {}

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->repository->dashboard();
    }
}
