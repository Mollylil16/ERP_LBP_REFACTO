<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Crm\CrmDashboardController;
use App\Controllers\Crm\CrmCallCenterController;

/** @var Router $router */

$router->group('/crm', function (Router $router): void {
    $router->get('/', [CrmDashboardController::class, 'index']);
    $router->get('/dashboard', [CrmDashboardController::class, 'index']);

    // Recherche Call Center & Rayons Temps Réel
    $router->get('/callcenter', [CrmCallCenterController::class, 'index']);
});
