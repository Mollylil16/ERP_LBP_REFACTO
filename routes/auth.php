<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Auth\AuthController;

/** @var Router $router */

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Choisir son mot de passe (obligatoire après un mot de passe provisoire)
$router->get('/compte/mot-de-passe', [\App\Controllers\Auth\MotDePasseController::class, 'show']);
$router->post('/compte/mot-de-passe', [\App\Controllers\Auth\MotDePasseController::class, 'update']);
