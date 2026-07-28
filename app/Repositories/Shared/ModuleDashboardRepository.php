<?php

declare(strict_types=1);

namespace App\Repositories\Shared;

use App\Services\Shared\ModuleDashboardService;
use PDO;

class ModuleDashboardRepository
{
    public function __construct(protected PDO $pdo)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function dashboardFor(string $slug): array
    {
        return (new ModuleDashboardService())->dashboard($slug);
    }
}
