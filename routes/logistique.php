<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Logistique\LogistiqueDashboardController;
use App\Controllers\Logistique\RayonsController;
use App\Controllers\Logistique\LogistiqueParametresController;

/** @var Router $router */

$router->group('/logistique', function (Router $router): void {
    $router->get('/', [LogistiqueDashboardController::class, 'index']);
    $router->get('/dashboard', [LogistiqueDashboardController::class, 'index']);

    // Rayons & Capacité de stockage
    $router->get('/rayons', [RayonsController::class, 'index']);
    $router->post('/rayons/enregistrer', [RayonsController::class, 'store']);
    $router->post('/rayons/{id}/supprimer', [RayonsController::class, 'delete']);

    // Délais & Frais de gardiennage
    $router->get('/parametres', [LogistiqueParametresController::class, 'index']);
    $router->post('/parametres/enregistrer', [LogistiqueParametresController::class, 'store']);
});
