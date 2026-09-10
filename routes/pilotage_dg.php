<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\PilotageDg\PilotageDgDashboardController;

/** @var Router $router */

$router->group('/pilotage-dg', function (Router $router): void {
    $router->get('/', [PilotageDgDashboardController::class, 'index']);
    $router->get('/dashboard', [PilotageDgDashboardController::class, 'index']);
    $router->get('/personnel', [PilotageDgDashboardController::class, 'personnel']);
    $router->get('/validations', [PilotageDgDashboardController::class, 'validations']);
    $router->get('/anomalies', [PilotageDgDashboardController::class, 'anomalies']);
    $router->get('/audit', [PilotageDgDashboardController::class, 'audit']);

    // Decisions prises depuis le Centre de Validation. La logique metier reste celle
    // du module RH : ces routes s'y delegent, elles ne la reimplementent pas.
    $router->post('/validations/workflow/{id}', [PilotageDgDashboardController::class, 'decideWorkflow']);
    $router->post('/validations/demande/{id}', [PilotageDgDashboardController::class, 'decideLegalRequest']);
});
