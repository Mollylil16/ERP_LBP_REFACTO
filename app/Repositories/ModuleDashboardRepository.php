<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\ModuleDashboardService;
use PDO;

abstract class ModuleDashboardRepository
{
    public function __construct(protected PDO $pdo) {}

    /**
     * @return array<string,mixed>
     */
    final protected function dashboardFor(string $moduleKey): array
    {
        /**
         * Le catalogue existant reste la source de vérité provisoire des dashboards.
         * Chaque repository dédié devient le point d'extension SQL du module.
         */
        return (new ModuleDashboardService())->dashboard($moduleKey);
    }

    /**
     * @return array<string,mixed>
     */
    abstract public function dashboard(): array;
}
