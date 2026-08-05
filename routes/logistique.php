<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Logistique\LogistiqueDashboardController;
use App\Controllers\Logistique\RayonsController;
use App\Controllers\Logistique\LogistiqueParametresController;
use App\Controllers\Logistique\LogistiqueColisageController;

use App\Controllers\Logistique\LogistiqueEmballagesController;

/** @var Router $router */

$router->group('/logistique', function (Router $router): void {
    $router->get('/', [LogistiqueDashboardController::class, 'index']);
    $router->get('/dashboard', [LogistiqueDashboardController::class, 'index']);

    // Suivi Colisage (vue transversale par agence + date, sans montant)
    $router->get('/colisage', [LogistiqueColisageController::class, 'index']);
    $router->get('/colisage/export-pdf', [LogistiqueColisageController::class, 'exportPdf']);
    $router->get('/colisage/export-excel', [LogistiqueColisageController::class, 'exportExcel']);

    // Rayons & Capacité de stockage
    $router->get('/rayons', [RayonsController::class, 'index']);
    $router->post('/rayons', [RayonsController::class, 'store']);
    $router->post('/rayons/enregistrer', [RayonsController::class, 'store']);
    $router->post('/rayons/{id}/supprimer', [RayonsController::class, 'delete']);

    // Emballages & Consommables LBP (Cartons, Bôrô, Valises, Sacs)
    $router->get('/emballages', [LogistiqueEmballagesController::class, 'index']);
    $router->post('/emballages/mouvement', [LogistiqueEmballagesController::class, 'store']);

    // Délais & Frais de gardiennage
    $router->get('/parametres', [LogistiqueParametresController::class, 'index']);
    $router->post('/parametres/enregistrer', [LogistiqueParametresController::class, 'store']);
});
