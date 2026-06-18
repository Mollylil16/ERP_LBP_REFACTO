<?php

namespace App\Controllers\Portal;

use App\Controllers\BaseController;

use App\Helpers\Auth;
use App\Middleware\AuthMiddleware;
use App\Security\PermissionAction;
use App\Security\PermissionEntityRegistry;
use App\Services\Shared\ModuleDashboardService;

/**
 * Portail central de sélection des modules ERP.
 *
 * Point d'entrée privé après connexion : connexion -> selection_portail -> module métier.
 */
class SelectionPortailController extends BaseController
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

        $user = [
            'id' => Auth::id(),
            'name' => Auth::user()?->fullName ?? 'Administrateur',
        ];

        $this->view('selection_portail/index', [
            'pageTitle' => 'Sélection portail',
            'user' => $user,
            'modules' => $modules,
        ]);
    }
}
