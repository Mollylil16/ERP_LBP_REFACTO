<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Entrepots\EntrepotsDashboardController;

/** @var Router $router */

$router->group('/entrepots', function (Router $router): void {
    $router->get('/', [EntrepotsDashboardController::class, 'index']);
    $router->get('/dashboard', [EntrepotsDashboardController::class, 'index']);
});
