<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Auth\AuthController;

/** @var Router $router */

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
