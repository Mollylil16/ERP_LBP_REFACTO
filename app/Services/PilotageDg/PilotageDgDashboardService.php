<?php

declare(strict_types=1);

namespace App\Services\PilotageDg;

use App\Repositories\PilotageDg\PilotageDgDashboardRepository;

final class PilotageDgDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(PilotageDgDashboardRepository $repository)
    {
        parent::__construct($repository);
    }

    /** @return array<string, mixed> */
    public function moduleMeta(): array
    {
        return $this->repository->moduleMeta();
    }

    /** @return array<string, mixed> */
    public function personnelSupervision(): array
    {
        return $this->repository->personnelSupervision();
    }

    /** @return array<string, mixed> */
    public function pendingValidations(): array
    {
        return $this->repository->pendingValidations();
    }

    /** @return array<string, mixed> */
    public function anomalies(): array
    {
        return $this->repository->anomalies();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function auditLog(array $filters, int $page = 1): array
    {
        return $this->repository->auditLog($filters, $page);
    }
}
