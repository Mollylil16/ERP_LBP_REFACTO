<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminPermissionController;
use App\Controllers\AdminUserController;
use App\Controllers\AdminSystemTestController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\RhDashboardController;
use App\Controllers\RhPersonnelController;
use App\Controllers\RhModuleController;
use App\Controllers\RhSettingsController;
use App\Controllers\RhLifecycleController;
use App\Controllers\SelectionPortailController;
use App\Controllers\WebsiteController;
use App\Controllers\EmployeePortalController;
use App\Controllers\FinanceDashboardController;
use App\Controllers\ColisageDashboardController;
use App\Controllers\LogistiqueDashboardController;
use App\Controllers\CrmDashboardController;
use App\Controllers\TicketsDashboardController;
use App\Controllers\SiteAdminDashboardController;
use App\Controllers\TransitDouaneDashboardController;
use App\Controllers\TrackingColisDashboardController;
use App\Controllers\FacturationDashboardController;
use App\Controllers\EntrepotsDashboardController;
use App\Controllers\FlotteTransportDashboardController;
use App\Controllers\PortefeuilleClientsDashboardController;
use App\Controllers\AgentsCorrespondantsDashboardController;
use App\Controllers\PilotageDgDashboardController;

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
    $router->get('/', [FinanceDashboardController::class, 'index']);
    $router->get('/dashboard', [FinanceDashboardController::class, 'index']);
});

$router->group('/colisage', function (Router $router): void {
    $router->get('/', [ColisageDashboardController::class, 'index']);
    $router->get('/dashboard', [ColisageDashboardController::class, 'index']);
});

$router->group('/logistique', function (Router $router): void {
    $router->get('/', [LogistiqueDashboardController::class, 'index']);
    $router->get('/dashboard', [LogistiqueDashboardController::class, 'index']);
});

$router->group('/espace-employe', function (Router $router): void {
    $router->get('/', [EmployeePortalController::class, 'index']);
    $router->get('/dashboard', [EmployeePortalController::class, 'index']);
    $router->get('/demandes/nouvelle', [EmployeePortalController::class, 'createRequest']);
    $router->post('/demandes', [EmployeePortalController::class, 'storeRequest']);
    $router->get('/demandes/{id}', [EmployeePortalController::class, 'showRequest']);
    $router->post('/demandes/{id}/annuler', [EmployeePortalController::class, 'cancelRequest']);
    $router->post('/explications/{id}/repondre', [EmployeePortalController::class, 'respondExplanation']);
});

$router->group('/crm', function (Router $router): void {
    $router->get('/', [CrmDashboardController::class, 'index']);
    $router->get('/dashboard', [CrmDashboardController::class, 'index']);
});

$router->group('/tickets', function (Router $router): void {
    $router->get('/', [TicketsDashboardController::class, 'index']);
    $router->get('/dashboard', [TicketsDashboardController::class, 'index']);
});

$router->group('/site-admin', function (Router $router): void {
    $router->get('/', [SiteAdminDashboardController::class, 'index']);
    $router->get('/dashboard', [SiteAdminDashboardController::class, 'index']);
});

$router->group('/transit-douane', function (Router $router): void {
    $router->get('/', [TransitDouaneDashboardController::class, 'index']);
    $router->get('/dashboard', [TransitDouaneDashboardController::class, 'index']);
});

$router->group('/tracking-colis', function (Router $router): void {
    $router->get('/', [TrackingColisDashboardController::class, 'index']);
    $router->get('/dashboard', [TrackingColisDashboardController::class, 'index']);
});

$router->group('/facturation', function (Router $router): void {
    $router->get('/', [FacturationDashboardController::class, 'index']);
    $router->get('/dashboard', [FacturationDashboardController::class, 'index']);
});

$router->group('/entrepots', function (Router $router): void {
    $router->get('/', [EntrepotsDashboardController::class, 'index']);
    $router->get('/dashboard', [EntrepotsDashboardController::class, 'index']);
});

$router->group('/flotte-transport', function (Router $router): void {
    $router->get('/', [FlotteTransportDashboardController::class, 'index']);
    $router->get('/dashboard', [FlotteTransportDashboardController::class, 'index']);
});

$router->group('/portefeuille-clients', function (Router $router): void {
    $router->get('/', [PortefeuilleClientsDashboardController::class, 'index']);
    $router->get('/dashboard', [PortefeuilleClientsDashboardController::class, 'index']);
});

$router->group('/agents-correspondants', function (Router $router): void {
    $router->get('/', [AgentsCorrespondantsDashboardController::class, 'index']);
    $router->get('/dashboard', [AgentsCorrespondantsDashboardController::class, 'index']);
});

$router->group('/pilotage-dg', function (Router $router): void {
    $router->get('/', [PilotageDgDashboardController::class, 'index']);
    $router->get('/dashboard', [PilotageDgDashboardController::class, 'index']);
});

/** Site public séparé du backoffice ERP. */
$router->get('/site', [WebsiteController::class, 'publicSite']);
$router->get('/site/tracking', [WebsiteController::class, 'tracking']);
$router->get('/site/agences', [WebsiteController::class, 'agencies']);
$router->get('/site/devis', [WebsiteController::class, 'quote']);
$router->get('/site/contact', [WebsiteController::class, 'contact']);

/**
 * Routes RH (/rh)
 */
$router->group('/rh', function (Router $router): void {
    $router->get('/', [RhDashboardController::class, 'index']);
    $router->get('/dashboard', [RhDashboardController::class, 'index']);
    $router->get('/mutations', [RhPersonnelController::class, 'mutationsIndex']);
    $router->get('/mouvements', [RhPersonnelController::class, 'movementsIndex']);
    $router->get('/pointage', [RhModuleController::class, 'attendance']);
    $router->get('/contrats', [RhLifecycleController::class, 'index']);
    $router->get('/cycle-vie', [RhLifecycleController::class, 'index']);
    $router->post('/cycle-vie/contrats', [RhLifecycleController::class, 'storeContract']);
    $router->post('/cycle-vie/missions', [RhLifecycleController::class, 'storeAssignment']);
    $router->post('/cycle-vie/evaluations', [RhLifecycleController::class, 'storeEvaluation']);
    $router->post('/cycle-vie/formations', [RhLifecycleController::class, 'storeTraining']);
    $router->post('/cycle-vie/discipline', [RhLifecycleController::class, 'storeDiscipline']);
    $router->post('/cycle-vie/workflows/{id}', [RhLifecycleController::class, 'decideWorkflow']);
    $router->post('/cycle-vie/demandes-employes/{id}', [RhLifecycleController::class, 'decideEmployeeRequest']);
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

    $router->get('/system-tests', [AdminSystemTestController::class, 'index']);
    $router->get('/system-tests/latest', [AdminSystemTestController::class, 'latest']);
    $router->post('/system-tests/run', [AdminSystemTestController::class, 'runAll']);
    $router->post('/system-tests/run/{module}', [AdminSystemTestController::class, 'runModule']);

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
