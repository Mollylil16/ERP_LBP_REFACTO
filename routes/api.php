<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Api\PaymentApiController;
use App\Controllers\Api\TrackingApiController;

/** @var Router $router */

$router->group('/api', function (Router $router): void {
    $router->get('/paiements/pay/{id}', [PaymentApiController::class, 'pay']);
    $router->get('/paiements/qrcode/{id}', [PaymentApiController::class, 'qrcode']);
    $router->post('/paiements/callback', [PaymentApiController::class, 'callback']);

    // Public Real-Time Tracking & Webhook endpoints
    $router->get('/v1/tracking/{tracking}', [TrackingApiController::class, 'getTracking']);
    $router->post('/webhooks/tracking-update', [TrackingApiController::class, 'webhookUpdate']);
});
