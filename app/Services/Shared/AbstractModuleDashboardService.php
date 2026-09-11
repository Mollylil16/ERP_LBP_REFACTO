<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Repositories\Shared\ModuleDashboardRepository;

abstract class AbstractModuleDashboardService
{
    public function __construct(protected ModuleDashboardRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->repository->dashboard();
    }

    /** Libellé du périmètre affiché, pour le titre de l'écran. */
    public function nomAgence(?int $agenceId): string
    {
        return $this->repository->nomAgence($agenceId);
    }
}
