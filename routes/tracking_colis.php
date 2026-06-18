<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\TrackingColis\TrackingColisDashboardController;

/** @var Router $router */

$router->group('/tracking-colis', function (Router $router): void {
    $router->get('/', [TrackingColisDashboardController::class, 'index']);
    $router->get('/dashboard', [TrackingColisDashboardController::class, 'index']);
});
