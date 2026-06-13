<?php

use App\Modules\Uploads\Controllers\UploadsController;

/** @var \App\Core\Router $router */

$router->post('/api/uploads', [UploadsController::class, 'upload']);
