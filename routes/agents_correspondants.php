<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\AgentsCorrespondants\AgentsCorrespondantsDashboardController;

/** @var Router $router */

$router->group('/agents-correspondants', function (Router $router): void {
    $router->get('/', [AgentsCorrespondantsDashboardController::class, 'index']);
    $router->get('/dashboard', [AgentsCorrespondantsDashboardController::class, 'index']);
});
