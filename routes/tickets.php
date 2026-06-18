<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Tickets\TicketsDashboardController;

/** @var Router $router */

$router->group('/tickets', function (Router $router): void {
    $router->get('/', [TicketsDashboardController::class, 'index']);
    $router->get('/dashboard', [TicketsDashboardController::class, 'index']);
});
