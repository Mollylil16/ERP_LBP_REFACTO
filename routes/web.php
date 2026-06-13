<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\RhDashboardController;
use App\Controllers\RhPersonnelController;
use App\Controllers\SelectionPortailController;

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

$router->get('/selection_portail', [SelectionPortailController::class, 'index']);

$router->get('/dashboard', [DashboardController::class, 'index']);

$router->group('/rh', function (Router $router): void {
    $router->get('/', [RhDashboardController::class, 'index']);
    $router->get('/dashboard', [RhDashboardController::class, 'index']);
    $router->get('/mutations', [RhPersonnelController::class, 'mutationsIndex']);
    $router->get('/mouvements', [RhPersonnelController::class, 'movementsIndex']);

    $router->group('/personnel', function (Router $router): void {
        $router->get('/', [RhPersonnelController::class, 'index']);
        $router->get('/nouveau', [RhPersonnelController::class, 'create']);
        $router->post('/', [RhPersonnelController::class, 'store']);
        $router->get('/{id}', [RhPersonnelController::class, 'show']);
        $router->get('/{id}/modifier', [RhPersonnelController::class, 'edit']);
        $router->post('/{id}/modifier', [RhPersonnelController::class, 'update']);
        $router->get('/{id}/mutation', [RhPersonnelController::class, 'mutation']);
        $router->post('/{id}/mutation', [RhPersonnelController::class, 'applyMutation']);
        $router->get('/{id}/sortie', [RhPersonnelController::class, 'exit']);
        $router->post('/{id}/sortie', [RhPersonnelController::class, 'applyExit']);
        $router->post('/{id}/reintegration', [RhPersonnelController::class, 'reintegrate']);
        $router->post('/{id}/historique', [RhPersonnelController::class, 'addHistory']);
    });
});
