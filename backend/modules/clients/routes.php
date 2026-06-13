<?php

use App\Modules\Clients\Controllers\ClientsController;

/** @var \App\Core\Router $router */

$router->get('/api/clients', [ClientsController::class, 'index']);
$router->post('/api/clients', [ClientsController::class, 'create']);
