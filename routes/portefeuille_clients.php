<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\PortefeuilleClients\PortefeuilleClientsDashboardController;

/** @var Router $router */

$router->group('/portefeuille-clients', function (Router $router): void {
    $router->get('/', [PortefeuilleClientsDashboardController::class, 'index']);
    $router->get('/dashboard', [PortefeuilleClientsDashboardController::class, 'index']);
});
