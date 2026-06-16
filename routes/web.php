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
use App\Controllers\RhContractController;
use App\Controllers\RhLeaveController;
use App\Controllers\RhPayrollParameterController;
use App\Controllers\RhAttendanceController;
use App\Controllers\RhPayrollController;
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
    $router->get('/', [\App\Controllers\ColisageController::class, 'dashboard']);
    $router->get('/dashboard', [\App\Controllers\ColisageController::class, 'dashboard']);

    // Colis
    $router->get('/colis', [\App\Controllers\ColisageController::class, 'index']);
    $router->get('/colis/nouveau', [\App\Controllers\ColisageController::class, 'create']);
    $router->post('/colis', [\App\Controllers\ColisageController::class, 'store']);
    $router->get('/colis/{id}', [\App\Controllers\ColisageController::class, 'show']);
    $router->get('/colis/{id}/retrait', [\App\Controllers\ColisageController::class, 'showRetrait']);
    $router->post('/colis/{id}/retrait', [\App\Controllers\ColisageController::class, 'processRetrait']);
    $router->post('/colis/{id}/tracking', [\App\Controllers\ColisageController::class, 'addTrackingEvent']);

    // Expéditions / Manifestes
    $router->get('/expeditions', [\App\Controllers\ColisageController::class, 'expeditions']);
    $router->get('/expeditions/nouveau', [\App\Controllers\ColisageController::class, 'createExpedition']);
    $router->post('/expeditions', [\App\Controllers\ColisageController::class, 'storeExpedition']);
    $router->get('/expeditions/{id}', [\App\Controllers\ColisageController::class, 'showExpedition']);
    $router->post('/expeditions/{id}/statut', [\App\Controllers\ColisageController::class, 'updateExpeditionStatus']);
    $router->post('/expeditions/{id}/ajouter-colis', [\App\Controllers\ColisageController::class, 'assignColisExpedition']);

    // Inventaires
    $router->get('/inventaire', [\App\Controllers\ColisageController::class, 'inventaires']);
    $router->get('/inventaire/nouveau', [\App\Controllers\ColisageController::class, 'createInventaire']);
    $router->post('/inventaire', [\App\Controllers\ColisageController::class, 'storeInventaire']);
    $router->get('/inventaire/{id}', [\App\Controllers\ColisageController::class, 'showInventaire']);
    $router->post('/inventaire/{id}/scan', [\App\Controllers\ColisageController::class, 'scanInventaire']);
    $router->post('/inventaire/{id}/cloturer', [\App\Controllers\ColisageController::class, 'cloturerInventaire']);
});

$router->group('/flotte', function (Router $router): void {
    $router->get('/', [\App\Controllers\FlotteController::class, 'dashboard']);
    $router->get('/dashboard', [\App\Controllers\FlotteController::class, 'dashboard']);
});

$router->group('/tracking', function (Router $router): void {
    $router->get('/', [\App\Controllers\TrackingController::class, 'dashboard']);
    $router->get('/dashboard', [\App\Controllers\TrackingController::class, 'dashboard']);
});

$router->group('/entrepots', function (Router $router): void {
    $router->get('/', [\App\Controllers\EntrepotsController::class, 'dashboard']);
    $router->get('/dashboard', [\App\Controllers\EntrepotsController::class, 'dashboard']);
});

$router->group('/logistique', function (Router $router): void {
    $router->get('/', [\App\Controllers\LogistiqueController::class, 'dashboard']);
    $router->get('/dashboard', [\App\Controllers\LogistiqueController::class, 'dashboard']);

    // Prestataires
    $router->get('/prestataires', [\App\Controllers\LogistiqueController::class, 'prestataires']);
    $router->get('/prestataires/nouveau', [\App\Controllers\LogistiqueController::class, 'createPrestataire']);
    $router->post('/prestataires', [\App\Controllers\LogistiqueController::class, 'storePrestataire']);

    // Factures prestataires
    $router->get('/factures', [\App\Controllers\LogistiqueController::class, 'factures']);
    $router->get('/factures/nouvelle', [\App\Controllers\LogistiqueController::class, 'createFacture']);
    $router->post('/factures', [\App\Controllers\LogistiqueController::class, 'storeFacture']);
    $router->get('/factures/{id}', [\App\Controllers\LogistiqueController::class, 'showFacture']);

    // Retraits Hub
    $router->get('/retraits', [\App\Controllers\LogistiqueController::class, 'retraits']);
    $router->get('/retraits/nouveau/{factureId}', [\App\Controllers\LogistiqueController::class, 'createRetrait']);
    $router->post('/retraits', [\App\Controllers\LogistiqueController::class, 'storeRetrait']);
    $router->post('/retraits/{id}/approuver', [\App\Controllers\LogistiqueController::class, 'approuverRetrait']);
    $router->post('/retraits/{id}/refuser', [\App\Controllers\LogistiqueController::class, 'refuserRetrait']);

    // Fournitures
    $router->get('/fournitures', [\App\Controllers\LogistiqueController::class, 'fournitures']);
    $router->get('/fournitures/nouvelle', [\App\Controllers\LogistiqueController::class, 'createFourniture']);
    $router->post('/fournitures', [\App\Controllers\LogistiqueController::class, 'storeFourniture']);
    $router->post('/fournitures/{id}/valider', [\App\Controllers\LogistiqueController::class, 'validerFourniture']);
    $router->post('/fournitures/{id}/livrer', [\App\Controllers\LogistiqueController::class, 'livrerFourniture']);
    $router->post('/fournitures/{id}/rejeter', [\App\Controllers\LogistiqueController::class, 'rejeterFourniture']);

    // Crédits inter-agences
    $router->get('/credits', [\App\Controllers\LogistiqueController::class, 'credits']);
    $router->get('/credits/nouveau', [\App\Controllers\LogistiqueController::class, 'createCredit']);
    $router->post('/credits', [\App\Controllers\LogistiqueController::class, 'storeCredit']);
    $router->post('/credits/{id}/apurer', [\App\Controllers\LogistiqueController::class, 'apurerCredit']);
});

$router->group('/transit-douane', function (Router $router): void {
    $router->get('/', [\App\Controllers\TransitDouaneController::class, 'dashboard']);
    $router->get('/dashboard', [\App\Controllers\TransitDouaneController::class, 'dashboard']);
});

$router->group('/facturation', function (Router $router): void {
    $router->get('/', [\App\Controllers\FacturationController::class, 'dashboard']);
    $router->get('/dashboard', [\App\Controllers\FacturationController::class, 'dashboard']);
});

$router->group('/finance', function (Router $router): void {
    $router->get('/', [\App\Controllers\FinanceController::class, 'dashboard']);
    $router->get('/dashboard', [\App\Controllers\FinanceController::class, 'dashboard']);
});

$router->group('/espace-employe', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'employee']);
    $router->get('/dashboard', [BusinessModuleController::class, 'employee']);
});

$router->group('/crm', function (Router $router): void {
    $router->get('/', [\App\Controllers\CrmController::class, 'dashboard']);
    $router->get('/dashboard', [\App\Controllers\CrmController::class, 'dashboard']);
    $router->get('/clients', [\App\Controllers\CrmController::class, 'clients']);
    $router->get('/clients/nouveau', [\App\Controllers\CrmController::class, 'createClient']);
    $router->post('/clients', [\App\Controllers\CrmController::class, 'storeClient']);
});

$router->group('/tickets', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'tickets']);
    $router->get('/dashboard', [BusinessModuleController::class, 'tickets']);
});

$router->group('/site-admin', function (Router $router): void {
    $router->get('/', [BusinessModuleController::class, 'website']);
    $router->get('/dashboard', [BusinessModuleController::class, 'website']);
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

    /**
     * Routes pour la gestion des contrats. (/rh/contrats)
     */
    $router->group('/contrats', function (Router $router): void {
        $router->get('/', [RhContractController::class, 'index']);
        $router->get('/nouveau', [RhContractController::class, 'create']);
        $router->post('/', [RhContractController::class, 'store']);
        $router->get('/{id}', [RhContractController::class, 'show']);
        $router->get('/{id}/modifier', [RhContractController::class, 'edit']);
        $router->post('/{id}/modifier', [RhContractController::class, 'update']);
    });

    /**
     * Routes pour la gestion des paramètres de paie. (/rh/parametres-paie)
     */
    $router->group('/parametres-paie', function (Router $router): void {
        $router->get('/', [RhPayrollParameterController::class, 'index']);
        $router->get('/nouveau', [RhPayrollParameterController::class, 'create']);
        $router->post('/', [RhPayrollParameterController::class, 'store']);
        $router->get('/{id}/modifier', [RhPayrollParameterController::class, 'edit']);
        $router->post('/{id}/modifier', [RhPayrollParameterController::class, 'update']);
    });

    /**
     * Routes pour la gestion du pointage. (/rh/pointage)
     */
    $router->group('/pointage', function (Router $router): void {
        $router->get('/', [RhAttendanceController::class, 'index']);
        $router->get('/import', [RhAttendanceController::class, 'importForm']);
        $router->post('/import', [RhAttendanceController::class, 'import']);
        $router->get('/nouveau', [RhAttendanceController::class, 'create']);
        $router->post('/', [RhAttendanceController::class, 'store']);
    });

    /**
     * Routes pour la gestion de la paie. (/rh/paie)
     */
    $router->group('/paie', function (Router $router): void {
        $router->get('/', [RhPayrollController::class, 'index']);
        $router->post('/campagnes', [RhPayrollController::class, 'createCampaign']);
        $router->post('/campagnes/{id}/generer', [RhPayrollController::class, 'generate']);
        $router->get('/campagnes/{id}/bulletins', [RhPayrollController::class, 'showCampaignPayslips']);
        $router->get('/bulletins', [RhPayrollController::class, 'listPayslips']);
        $router->get('/bulletins/{id}', [RhPayrollController::class, 'showPayslip']);
    });

    $router->group('/conges', function (Router $router): void {
        $router->get('/', [RhLeaveController::class, 'index']);
        $router->get('/nouveau', [RhLeaveController::class, 'create']);
        $router->post('/nouveau', [RhLeaveController::class, 'store']);
        $router->post('/{id}/valider', [RhLeaveController::class, 'approve']);
        $router->post('/{id}/refuser', [RhLeaveController::class, 'reject']);
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
