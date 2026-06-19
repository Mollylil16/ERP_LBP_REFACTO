<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\SiteAdmin\SiteAdminDashboardController;

/** @var Router $router */

$router->group('/site-admin', function (Router $router): void {
    $router->get('/', [SiteAdminDashboardController::class, 'index']);
    $router->get('/dashboard', [SiteAdminDashboardController::class, 'index']);
    $router->get('/configuration', [SiteAdminDashboardController::class, 'configuration']);
    $router->post('/configuration/branding', [SiteAdminDashboardController::class, 'updateBranding']);
    $router->post('/configuration/slides', [SiteAdminDashboardController::class, 'saveSlide']);
    $router->post('/configuration/products', [SiteAdminDashboardController::class, 'saveProduct']);
});
