<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Api\PaymentApiController;

/** @var Router $router */

$router->group('/api', function (Router $router): void {
    $router->get('/paiements/pay/{id}', [PaymentApiController::class, 'pay']);
    $router->get('/paiements/qrcode/{id}', [PaymentApiController::class, 'qrcode']);
    $router->post('/paiements/callback', [PaymentApiController::class, 'callback']);
});
