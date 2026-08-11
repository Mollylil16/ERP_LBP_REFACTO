<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\CallCenter\CallCenterController;

/** @var Router $router */

$router->group('/call-center', function (Router $router): void {
    $router->get('/', [CallCenterController::class, 'index']);
    $router->get('/dashboard', [CallCenterController::class, 'index']);

    // Appels
    $router->get('/appels', [CallCenterController::class, 'appels']);
    $router->post('/appels/enregistrer', [CallCenterController::class, 'storeAppel']);
    $router->post('/appels/{id}/supprimer', [CallCenterController::class, 'deleteAppel']);

    // Litiges
    $router->get('/litiges', [CallCenterController::class, 'litiges']);
    $router->post('/litiges/enregistrer', [CallCenterController::class, 'storeLitige']);
    $router->post('/litiges/{id}/resoudre', [CallCenterController::class, 'resolveLitige']);
    $router->post('/litiges/{id}/supprimer', [CallCenterController::class, 'deleteLitige']);

    // Vue Rayons Temps Réel
    $router->get('/rayons', [CallCenterController::class, 'rayons']);

    // Recherche colis (tracking / téléphone / nom) — déplacée depuis CRM
    $router->get('/recherche-colis', [CallCenterController::class, 'rechercheColis']);

    // Suivi et Relances (WhatsApp/SMS/Appels)
    $router->get('/suivi', [CallCenterController::class, 'suivi']);
    $router->get('/suivi-departs', [CallCenterController::class, 'suiviDeparts']);
    $router->get('/suivi-departs/export-pdf', [CallCenterController::class, 'exportSuiviDepartsPdf']);
    $router->get('/suivi-departs/export-excel', [CallCenterController::class, 'exportSuiviDepartsExcel']);
    $router->post('/suivi/notifier', [CallCenterController::class, 'notifier']);
});

