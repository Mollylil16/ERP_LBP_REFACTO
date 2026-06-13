<?php

use App\Router;
use App\Modules\Rh\Controllers\RhController;
use App\Modules\Rh\Controllers\OperatorController;

$router->get('/api/rh', [RhController::class, 'index']);
$router->get('/api/rh/users', [RhController::class, 'listUsers']);
$router->get('/api/rh/users/:id', [RhController::class, 'getUser']);
$router->post('/api/rh/users', [RhController::class, 'createUser']);
$router->patch('/api/rh/users/:id', [RhController::class, 'updateUser']);
$router->patch('/api/rh/users/:id/toggle-active', [RhController::class, 'toggleActive']);
$router->delete('/api/rh/users/:id', [RhController::class, 'deleteUser']);

// Operateurs (Compte Unique)
$router->get('/api/rh/operateurs', [OperatorController::class, 'listOperateurs']);
$router->post('/api/rh/operateurs', [OperatorController::class, 'createOperateur']);
$router->patch('/api/rh/operateurs/:id/toggle', [OperatorController::class, 'toggleOperateur']);

$router->post('/api/rh/users/:id/reset-password', [RhController::class, 'resetPassword']);
$router->post('/api/rh/users/:id/change-password', [RhController::class, 'changePassword']);
$router->post('/api/rh/users/:id/select-agence', [RhController::class, 'selectAgence']);
$router->get('/api/rh/roles', [RhController::class, 'listRoles']);
$router->get('/api/rh/agences', [RhController::class, 'listAgences']);
$router->get('/api/rh/permissions', [RhController::class, 'listPermissions']);
