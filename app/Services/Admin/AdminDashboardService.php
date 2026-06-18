<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\Admin\AdminDashboardRepository;

final class AdminDashboardService
{
    public function __construct(private AdminDashboardRepository $repository) {}

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return $this->repository->dashboard();
    }
}
