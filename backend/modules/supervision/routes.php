<?php

use App\Modules\Supervision\Controllers\SupervisionController;

/** @var \App\Core\Router $router */

$router->get('/api/supervision/kpis', [SupervisionController::class, 'getKpisConsolides']);
$router->post('/api/supervision/signalements', [SupervisionController::class, 'signalerAnomalie']);
$router->post('/api/supervision/justifications', [SupervisionController::class, 'demanderJustification']);
$router->post('/api/supervision/annotations', [SupervisionController::class, 'annoterOperation']);
