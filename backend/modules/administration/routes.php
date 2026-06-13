<?php

use App\Router;
use App\Modules\Administration\Controllers\DashboardController;

$router->get('/api/admin/dashboard', [DashboardController::class, 'getDashboardStats']);
$router->get('/api/admin/tracking-employes', [DashboardController::class, 'getTrackingEmployes']);
