<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Logistique\LogistiqueDashboardController;

/** @var Router $router */

$router->group('/logistique', function (Router $router): void {
    $router->get('/', [LogistiqueDashboardController::class, 'index']);
    $router->get('/dashboard', [LogistiqueDashboardController::class, 'index']);
});
