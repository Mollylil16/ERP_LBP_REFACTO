<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Finance\FinanceDashboardController;

/** @var Router $router */

$router->group('/finance', function (Router $router): void {
    $router->get('/', [FinanceDashboardController::class, 'index']);
    $router->get('/dashboard', [FinanceDashboardController::class, 'index']);
});
