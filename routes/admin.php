<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminPermissionController;
use App\Controllers\Admin\AdminUserController;
use App\Controllers\Admin\AdminSystemTestController;

/** @var Router $router */

$router->group('/admin', function (Router $router): void {
    $router->get('/', [AdminDashboardController::class, 'index']);
    $router->get('/dashboard', [AdminDashboardController::class, 'index']);
    $router->get('/permissions', [AdminPermissionController::class, 'matrix']);

    $router->get('/system-tests', [AdminSystemTestController::class, 'index']);
    $router->get('/system-tests/latest', [AdminSystemTestController::class, 'latest']);
    $router->post('/system-tests/run', [AdminSystemTestController::class, 'runAll']);
    $router->post('/system-tests/run/{module}', [AdminSystemTestController::class, 'runModule']);

    $router->group('/users', function (Router $router): void {
        $router->get('/', [AdminUserController::class, 'index']);
        $router->get('/nouveau', [AdminUserController::class, 'create']);
        $router->post('/', [AdminUserController::class, 'store']);
        $router->get('/{id}', [AdminUserController::class, 'show']);
        $router->get('/{id}/modifier', [AdminUserController::class, 'edit']);
        $router->post('/{id}/modifier', [AdminUserController::class, 'update']);
        $router->post('/{id}/desactiver', [AdminUserController::class, 'deactivate']);
        $router->post('/{id}/activer', [AdminUserController::class, 'activate']);
        $router->get('/{id}/permissions', [AdminPermissionController::class, 'edit']);
        $router->post('/{id}/permissions', [AdminPermissionController::class, 'update']);
    });
});
