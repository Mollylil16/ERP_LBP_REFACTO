<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Crm\CrmDashboardController;
use App\Controllers\Crm\CrmClientController;

/** @var Router $router */

$router->group('/crm', function (Router $router): void {
    $router->get('/', [CrmDashboardController::class, 'index']);
    $router->get('/dashboard', [CrmDashboardController::class, 'index']);

    // Annuaire clients (unifié sur lbp_clients)
    $router->get('/clients', [CrmClientController::class, 'index']);
    $router->get('/clients/nouveau', [CrmClientController::class, 'create']);
    $router->post('/clients/enregistrer', [CrmClientController::class, 'store']);
    $router->get('/clients/{id}', [CrmClientController::class, 'show']);
    $router->post('/clients/{id}/modifier', [CrmClientController::class, 'update']);
    $router->post('/clients/{id}/interactions', [CrmClientController::class, 'storeInteraction']);
    $router->post('/clients/{id}/opportunites', [CrmClientController::class, 'storeOpportunity']);
    $router->post('/opportunites/{id}/etape', [CrmClientController::class, 'updateOpportunityStage']);
});
