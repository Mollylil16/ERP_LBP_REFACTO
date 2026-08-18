<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Surveillance\SurveillanceController;

/** @var Router $router */

$router->group('/surveillance', function (Router $router): void {
    $router->get('/', [SurveillanceController::class, 'dashboard']);
    $router->get('/dashboard', [SurveillanceController::class, 'dashboard']);
    
    $router->get('/alertes/{id}', [SurveillanceController::class, 'alerteShow']);
    $router->post('/alertes/{id}/traiter', [SurveillanceController::class, 'alerteTraiter']);
    
    $router->get('/employes/{id}', [SurveillanceController::class, 'employeShow']);
    
    $router->get('/config', [SurveillanceController::class, 'configRegles']);
    $router->post('/config/{code}/update', [SurveillanceController::class, 'configReglesUpdate']);
    
    $router->get('/recommandations', [SurveillanceController::class, 'recommandations']);
    $router->post('/recommandations/{id}/approuver', [SurveillanceController::class, 'approuverRecommandation']);
    $router->post('/recommandations/{id}/rejeter', [SurveillanceController::class, 'rejeterRecommandation']);
    $router->post('/train-ml', [SurveillanceController::class, 'trainMl']);
    
    $router->get('/integrite', [SurveillanceController::class, 'verifyIntegrite']);
    $router->get('/export-pdf', [SurveillanceController::class, 'exportPdf']);
    $router->get('/export-excel', [SurveillanceController::class, 'exportExcel']);
});
