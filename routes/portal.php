<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Core\DashboardController;
use App\Controllers\Portal\SelectionPortailController;

/** @var Router $router */

$router->get('/selection_portail', [SelectionPortailController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);
