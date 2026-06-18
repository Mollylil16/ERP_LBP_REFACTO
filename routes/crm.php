<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Crm\CrmDashboardController;

/** @var Router $router */

$router->group('/crm', function (Router $router): void {
    $router->get('/', [CrmDashboardController::class, 'index']);
    $router->get('/dashboard', [CrmDashboardController::class, 'index']);
});
