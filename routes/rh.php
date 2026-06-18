<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Rh\RhDashboardController;
use App\Controllers\Rh\RhPersonnelController;
use App\Controllers\Rh\RhModuleController;
use App\Controllers\Rh\RhSettingsController;
use App\Controllers\Rh\RhLifecycleController;

/** @var Router $router */

$router->group('/rh', function (Router $router): void {
    $router->get('/', [RhDashboardController::class, 'index']);
    $router->get('/dashboard', [RhDashboardController::class, 'index']);
    $router->get('/mutations', [RhPersonnelController::class, 'mutationsIndex']);
    $router->get('/mouvements', [RhPersonnelController::class, 'movementsIndex']);
    $router->get('/pointage', [RhModuleController::class, 'attendance']);
    $router->get('/contrats', [RhLifecycleController::class, 'index']);
    $router->get('/cycle-vie', [RhLifecycleController::class, 'index']);
    $router->post('/cycle-vie/contrats', [RhLifecycleController::class, 'storeContract']);
    $router->post('/cycle-vie/missions', [RhLifecycleController::class, 'storeAssignment']);
    $router->post('/cycle-vie/evaluations', [RhLifecycleController::class, 'storeEvaluation']);
    $router->post('/cycle-vie/formations', [RhLifecycleController::class, 'storeTraining']);
    $router->post('/cycle-vie/discipline', [RhLifecycleController::class, 'storeDiscipline']);
    $router->post('/cycle-vie/workflows/{id}', [RhLifecycleController::class, 'decideWorkflow']);
    $router->post('/cycle-vie/demandes-employes/{id}', [RhLifecycleController::class, 'decideEmployeeRequest']);
    $router->get('/paie', [RhModuleController::class, 'payroll']);
    $router->get('/parametrage', [RhSettingsController::class, 'index']);
    $router->post('/parametrage', [RhSettingsController::class, 'store']);
    $router->post('/parametrage/toggle', [RhSettingsController::class, 'toggle']);

    $router->group('/personnel', function (Router $router): void {
        $router->get('/', [RhPersonnelController::class, 'index']);
        $router->get('/nouveau', [RhPersonnelController::class, 'create']);
        $router->post('/', [RhPersonnelController::class, 'store']);
        $router->get('/{id}', [RhPersonnelController::class, 'show']);
        $router->get('/{id}/modifier', [RhPersonnelController::class, 'edit']);
        $router->post('/{id}/modifier', [RhPersonnelController::class, 'update']);
        $router->get('/{id}/mutation', [RhPersonnelController::class, 'mutation']);
        $router->post('/{id}/mutation', [RhPersonnelController::class, 'applyMutation']);
        $router->get('/{id}/sortie', [RhPersonnelController::class, 'exit']);
        $router->post('/{id}/sortie', [RhPersonnelController::class, 'applyExit']);
        $router->post('/{id}/reintegration', [RhPersonnelController::class, 'reintegrate']);
        $router->post('/{id}/historique', [RhPersonnelController::class, 'addHistory']);
    });
});
