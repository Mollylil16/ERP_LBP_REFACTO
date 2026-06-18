<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\SiteAdmin\SiteAdminDashboardController;

/** @var Router $router */

$router->group('/site-admin', function (Router $router): void {
    $router->get('/', [SiteAdminDashboardController::class, 'index']);
    $router->get('/dashboard', [SiteAdminDashboardController::class, 'index']);
});
