<?php

use App\Modules\Litiges\Controllers\LitigesController;

/** @var \App\Core\Router $router */

$router->get('/api/litiges', [LitigesController::class, 'index']);
$router->post('/api/litiges', [LitigesController::class, 'create']);
