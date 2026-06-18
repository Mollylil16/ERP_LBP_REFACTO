<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Employee\EmployeePortalController;

/** @var Router $router */

$router->group('/espace-employe', function (Router $router): void {
    $router->get('/', [EmployeePortalController::class, 'index']);
    $router->get('/dashboard', [EmployeePortalController::class, 'index']);
    $router->get('/demandes/nouvelle', [EmployeePortalController::class, 'createRequest']);
    $router->post('/demandes', [EmployeePortalController::class, 'storeRequest']);
    $router->get('/demandes/{id}', [EmployeePortalController::class, 'showRequest']);
    $router->post('/demandes/{id}/annuler', [EmployeePortalController::class, 'cancelRequest']);
    $router->post('/explications/{id}/repondre', [EmployeePortalController::class, 'respondExplanation']);
});
