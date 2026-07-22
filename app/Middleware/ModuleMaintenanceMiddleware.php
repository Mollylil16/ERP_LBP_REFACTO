<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\Database;
use App\Repositories\Admin\ModuleMaintenanceRepository;
use App\Services\Admin\ModuleMaintenanceService;

final class ModuleMaintenanceMiddleware
{
    /** @return array<string,mixed>|null */
    public static function stateForPath(string $path): ?array
    {
        $slug = self::slugForPath($path);
        if ($slug === null) {
            return null;
        }
        $state = (new ModuleMaintenanceService(
            new ModuleMaintenanceRepository(Database::getConnection())
        ))->state($slug);

        return !empty($state['is_maintenance']) ? ['slug' => $slug] + $state : null;
    }

    private static function slugForPath(string $path): ?string
    {
        if (str_starts_with($path, '/admin/system-tests')) {
            return null;
        }
        if (str_starts_with($path, '/site-admin') || $path === '/site' || str_starts_with($path, '/site/')) {
            return 'site-admin';
        }
        if (str_starts_with($path, '/espace-employe')) {
            return 'espace-employe';
        }
        foreach ([
            'rh', 'finance', 'colisage', 'logistique', 'crm', 'tickets',
            'transit-douane', 'tracking-colis', 'facturation', 'entrepots',
            'flotte-transport', 'portefeuille-clients', 'agents-correspondants',
            'pilotage-dg',
        ] as $slug) {
            if ($path === '/' . $slug || str_starts_with($path, '/' . $slug . '/')) {
                return $slug;
            }
        }
        return null;
    }
}
