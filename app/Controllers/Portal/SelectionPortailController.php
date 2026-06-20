<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Admin\ModuleMaintenanceRepository;
use App\Security\PermissionAction;
use App\Security\PermissionEntityRegistry;
use App\Services\Admin\ModuleMaintenanceService;
use App\Services\Shared\ModuleDashboardService;
use App\View\Pages\Portal\SelectionPage;

/**
 * Point d’entrée privé après connexion : connexion -> portail -> module métier.
 */
final class SelectionPortailController extends BaseController
{
    public function index(): void
    {
        AuthMiddleware::check();

        $modules = (new ModuleDashboardService())->portalModules();
        array_splice($modules, 1, 0, [[
            'key' => 'rh',
            'label' => 'RH',
            'code' => 'RH',
            'icon' => 'rh',
            'description' => 'Employés, présences, contrats, demandes administratives, rôles opérationnels et habilitations internes.',
            'url' => '/rh/dashboard',
            'class' => 'module-rh',
            'status' => 'Socle RH',
            'keywords' => 'rh ressources humaines employés présence contrats congés demandes personnel habilitations',
        ]]);

        $modules[] = [
            'key' => 'admin',
            'label' => 'Admin',
            'code' => 'ADM',
            'icon' => 'admin',
            'description' => 'Utilisateurs, droits, paramètres société, sécurité, référentiels, journaux d’audit et configuration globale.',
            'url' => '/admin/dashboard',
            'class' => 'module-admin',
            'status' => 'Noyau système',
            'keywords' => 'admin administration utilisateurs droits rôles permissions paramètres sécurité audit configuration',
        ];

        $modules = array_values(array_filter($modules, static function (array $module): bool {
            if ($module['key'] === 'admin') {
                return Auth::user()?->isAdmin ?? false;
            }
            if ($module['key'] === 'rh') {
                $requirements = array_fill_keys(
                    PermissionEntityRegistry::codesForModule('Ressources humaines'),
                    PermissionAction::VIEW
                );
                return Auth::canAny($requirements);
            }
            return true;
        }));

        foreach ($modules as &$module) {
            if ($module['key'] === 'rh' && !Auth::can(PermissionEntityRegistry::RH_EMPLOYEES)) {
                $module['url'] = '/rh/personnel';
            }
        }
        unset($module);

        $maintenanceStates = (new ModuleMaintenanceService(
            new ModuleMaintenanceRepository(Database::getConnection())
        ))->states();

        foreach ($modules as &$module) {
            $slug = match ((string) $module['key']) {
                'employee' => 'espace-employe',
                default => (string) $module['key'],
            };
            $state = $maintenanceStates[$slug] ?? [];
            $module['is_maintenance'] = (bool) ($state['is_maintenance'] ?? false);
            $module['maintenance_reason'] = (string) ($state['reason'] ?? '');
            if ($module['is_maintenance']) {
                $module['status'] = 'En maintenance';
                $module['url'] = '';
            }
        }
        unset($module);

        $this->view('selection_portail/index', [
            'page' => new SelectionPage(
                Auth::user()?->fullName ?? 'Administrateur',
                $modules,
            ),
        ]);
    }
}
