<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Colisage\ColisageDashboardController;

/** @var Router $router */

$router->group('/colisage', function (Router $router): void {
    $router->get('/', [ColisageDashboardController::class, 'index']);
    $router->get('/dashboard', [ColisageDashboardController::class, 'index']);
});
