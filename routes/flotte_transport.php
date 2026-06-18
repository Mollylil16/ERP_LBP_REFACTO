<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\FlotteTransport\FlotteTransportDashboardController;

/** @var Router $router */

$router->group('/flotte-transport', function (Router $router): void {
    $router->get('/', [FlotteTransportDashboardController::class, 'index']);
    $router->get('/dashboard', [FlotteTransportDashboardController::class, 'index']);
});
