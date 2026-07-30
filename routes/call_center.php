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

    // Litiges
    $router->get('/litiges', [CallCenterController::class, 'litiges']);
    $router->post('/litiges/enregistrer', [CallCenterController::class, 'storeLitige']);
    $router->post('/litiges/{id}/resoudre', [CallCenterController::class, 'resolveLitige']);

    // Vue Rayons Temps Réel
    $router->get('/rayons', [CallCenterController::class, 'rayons']);

    // Suivi et Relances (WhatsApp/SMS/Appels)
    $router->get('/suivi', [CallCenterController::class, 'suivi']);
    $router->post('/suivi/notifier', [CallCenterController::class, 'notifier']);
});

