<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Facturation\FacturationDashboardController;
use App\Controllers\Facturation\FacturationFilterController;
use App\Controllers\Facturation\FactureEditController;

/** @var Router $router */

$router->group('/facturation', function (Router $router): void {
    $router->get('/', [FacturationDashboardController::class, 'index']);
    $router->get('/dashboard', [FacturationDashboardController::class, 'index']);

    // Écran de filtre par période / agence / trajet + exports PDF & Excel (avec montants)
    $router->get('/filtre', [FacturationFilterController::class, 'index']);
    $router->get('/filtre/export-pdf', [FacturationFilterController::class, 'exportPdf']);
    $router->get('/filtre/export-excel', [FacturationFilterController::class, 'exportExcel']);

    // Consultation & modification d'une facture verrouillée (Responsable/Admin uniquement + audit log)
    $router->get('/factures/{id}/modifier', [FactureEditController::class, 'edit']);
    $router->post('/factures/{id}/modifier', [FactureEditController::class, 'update']);
});
