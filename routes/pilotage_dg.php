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
});
