<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\AgentsCorrespondants\AgentsCorrespondantsDashboardController;

/** @var Router $router */

$router->group('/agents-correspondants', function (Router $router): void {
    $router->get('/', [AgentsCorrespondantsDashboardController::class, 'index']);
    $router->get('/dashboard', [AgentsCorrespondantsDashboardController::class, 'index']);

    // Ecriture : le controle de role et le jeton CSRF sont verifies dans le controleur.
    $router->post('/nouveau', [AgentsCorrespondantsDashboardController::class, 'store']);
    $router->post('/{id}/modifier', [AgentsCorrespondantsDashboardController::class, 'update']);
    $router->post('/{id}/desactiver', [AgentsCorrespondantsDashboardController::class, 'deactivate']);
});
