<?php

use App\Modules\Tarifs\Controllers\TarifsController;

/** @var \App\Core\Router $router */

$router->get('/api/tarifs', [TarifsController::class, 'index']);
$router->post('/api/tarifs', [TarifsController::class, 'create']);
$router->get('/api/tarifs/calculer', [TarifsController::class, 'calculer']);
