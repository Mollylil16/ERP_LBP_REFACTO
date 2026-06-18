<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\TransitDouane\TransitDouaneDashboardController;

/** @var Router $router */

$router->group('/transit-douane', function (Router $router): void {
    $router->get('/', [TransitDouaneDashboardController::class, 'index']);
    $router->get('/dashboard', [TransitDouaneDashboardController::class, 'index']);
});
