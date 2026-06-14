<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminPermissionController;
use App\Controllers\AdminUserController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\RhDashboardController;
use App\Controllers\RhPersonnelController;
use App\Controllers\RhModuleController;
use App\Controllers\RhSettingsController;
use App\Controllers\SelectionPortailController;
use App\Controllers\BusinessModuleController;
use App\Controllers\WebsiteController;

/** @var Router $router */

/*
|--------------------------------------------------------------------------
| Routes publiques
|--------------------------------------------------------------------------
|
| Ces routes sont accessibles sans connexion.
|
*/

/**
 * Route de la page d’accueil.
 */
$router->get('/', [HomeController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Routes d’authentification
|--------------------------------------------------------------------------
|
| Ces routes permettent à l’utilisateur de créer un compte, se connecter
| et se déconnecter.
|
*/


/**
 * Routes d’inscription.
 */

/**
 * Routes de connexion.
 */
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);

/**
 * Route de déconnexion.
 */
$router->get('/logout', [AuthController::class, 'logout']);

/*
|--------------------------------------------------------------------------
| Routes espace utilisateur
|--------------------------------------------------------------------------
|
| Ces routes concerneront les pages accessibles après connexion.
| Pour l’instant, la protection réelle sera ajoutée avec un middleware Auth.
|
*/

/**
 * Route du portail de sélection des modules ERP.
 */
$router->get('/selection_portail', [SelectionPortailController::class, 'index']);


/**
 * Route du tableau de bord principal.
 */
$router->get('/dashboard', [DashboardController::class, 'index']);



/**
 * Routes modules métiers ERP.
 * Chaque module dispose au minimum de /nom-module et /nom-module/dashboard.
 */
$router->group('/finance', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'finance']);
    $router->get('/dashboard', [BusinessModuleController::class, 'finance']);
});

$router->group('/colisage', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'colisage']);
    $router->get('/dashboard', [BusinessModuleController::class, 'colisage']);
});

$router->group('/logistique', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'logistique']);
    $router->get('/dashboard', [BusinessModuleController::class, 'logistique']);
});

$router->group('/espace-employe', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'employee']);
    $router->get('/dashboard', [BusinessModuleController::class, 'employee']);
});

$router->group('/crm', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'crm']);
    $router->get('/dashboard', [BusinessModuleController::class, 'crm']);
});

$router->group('/tickets', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'tickets']);
    $router->get('/dashboard', [BusinessModuleController::class, 'tickets']);
});

$router->group('/site-admin', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'website']);
    $router->get('/dashboard', [BusinessModuleController::class, 'website']);
});

$router->group('/transit-douane', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'customs']);
    $router->get('/dashboard', [BusinessModuleController::class, 'customs']);
});

$router->group('/tracking-colis', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'tracking']);
    $router->get('/dashboard', [BusinessModuleController::class, 'tracking']);
});

$router->group('/facturation', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'billing']);
    $router->get('/dashboard', [BusinessModuleController::class, 'billing']);
});

$router->group('/entrepots', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'warehouses']);
    $router->get('/dashboard', [BusinessModuleController::class, 'warehouses']);
});

$router->group('/flotte-transport', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'fleet']);
    $router->get('/dashboard', [BusinessModuleController::class, 'fleet']);
});

$router->group('/portefeuille-clients', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'clientPortfolio']);
    $router->get('/dashboard', [BusinessModuleController::class, 'clientPortfolio']);
});

$router->group('/agents-correspondants', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'agents']);
    $router->get('/dashboard', [BusinessModuleController::class, 'agents']);
});

$router->group('/pilotage-dg', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'executiveCenter']);
    $router->get('/dashboard', [BusinessModuleController::class, 'executiveCenter']);
});

/** Site public séparé du backoffice ERP. */
$router->get('/site', [WebsiteController::class, 'publicSite']);
$router->get('/site/tracking', [WebsiteController::class, 'publicSite']);
$router->get('/site/devis', [WebsiteController::class, 'publicSite']);
$router->get('/site/contact', [WebsiteController::class, 'publicSite']);

/**
 * Routes RH (/rh)
 */
$router->group('/rh', function (Router $router): void {
    $router->get('/', [RhDashboardController::class, 'index']);
    $router->get('/dashboard', [RhDashboardController::class, 'index']);
    $router->get('/mutations', [RhPersonnelController::class, 'mutationsIndex']);
    $router->get('/mouvements', [RhPersonnelController::class, 'movementsIndex']);
    $router->get('/pointage', [RhModuleController::class, 'attendance']);
    $router->get('/contrats', [RhModuleController::class, 'contracts']);
    $router->get('/paie', [RhModuleController::class, 'payroll']);
    $router->get('/parametrage', [RhSettingsController::class, 'index']);
    $router->post('/parametrage', [RhSettingsController::class, 'store']);
    $router->post('/parametrage/toggle', [RhSettingsController::class, 'toggle']);

    /**
     * Routes pour la gestion du personnel. (/rh/personnel)
     */
    $router->group('/personnel', function (Router $router): void {
        $router->get('/', [RhPersonnelController::class, 'index']);
        $router->get('/nouveau', [RhPersonnelController::class, 'create']);
        $router->post('/', [RhPersonnelController::class, 'store']);
        $router->get('/{id}', [RhPersonnelController::class, 'show']);
        $router->get('/{id}/modifier', [RhPersonnelController::class, 'edit']);
        $router->post('/{id}/modifier', [RhPersonnelController::class, 'update']);
        $router->get('/{id}/mutation', [RhPersonnelController::class, 'mutation']);
        $router->post('/{id}/mutation', [RhPersonnelController::class, 'applyMutation']);
        $router->get('/{id}/sortie', [RhPersonnelController::class, 'exit']);
        $router->post('/{id}/sortie', [RhPersonnelController::class, 'applyExit']);
        $router->post('/{id}/reintegration', [RhPersonnelController::class, 'reintegrate']);
        $router->post('/{id}/historique', [RhPersonnelController::class, 'addHistory']);
    });
});

/**
 * Routes Administration (/admin)
 */
$router->group('/admin', function (Router $router): void {
    $router->get('/', [AdminDashboardController::class, 'index']);
    $router->get('/dashboard', [AdminDashboardController::class, 'index']);
    $router->get('/permissions', [AdminPermissionController::class, 'matrix']);

    $router->group('/users', function (Router $router): void {
        $router->get('/', [AdminUserController::class, 'index']);
        $router->get('/nouveau', [AdminUserController::class, 'create']);
        $router->post('/', [AdminUserController::class, 'store']);
        $router->get('/{id}', [AdminUserController::class, 'show']);
        $router->get('/{id}/modifier', [AdminUserController::class, 'edit']);
        $router->post('/{id}/modifier', [AdminUserController::class, 'update']);
        $router->post('/{id}/desactiver', [AdminUserController::class, 'deactivate']);
        $router->post('/{id}/activer', [AdminUserController::class, 'activate']);
        $router->get('/{id}/permissions', [AdminPermissionController::class, 'edit']);
        $router->post('/{id}/permissions', [AdminPermissionController::class, 'update']);
    });
});
