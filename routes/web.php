<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;

/** @var Router $router */

/*
|--------------------------------------------------------------------------
| Routes publiques
|--------------------------------------------------------------------------
|
| Ces routes sont accessibles sans connexion.
|
*/

$router->get('/', [HomeController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Routes d’authentification
|--------------------------------------------------------------------------
|
| Ces routes permettent à l’utilisateur de créer un compte, se connecter
| et se déconnecter.
|
*/

$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);

$router->get('/logout', [AuthController::class, 'logout']);

/*
|--------------------------------------------------------------------------
| Routes espace utilisateur
|--------------------------------------------------------------------------
|
| Ces routes concerneront les pages accessibles après connexion.
| Pour l’instant, la protection réelle sera ajoutée avec un middleware Auth.
|
*/

$router->get('/dashboard', [DashboardController::class, 'index']);
