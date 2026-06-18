<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Facturation\FacturationDashboardController;

/** @var Router $router */

$router->group('/facturation', function (Router $router): void {
    $router->get('/', [FacturationDashboardController::class, 'index']);
    $router->get('/dashboard', [FacturationDashboardController::class, 'index']);
});
