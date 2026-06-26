<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Rh\RhDashboardController;
use App\Controllers\Rh\RhPersonnelController;
use App\Controllers\Rh\RhModuleController;
use App\Controllers\Rh\RhSettingsController;
use App\Controllers\Rh\RhLifecycleController;
use App\Controllers\Rh\RhHolidayController;
use App\Controllers\Rh\RhSignatoryController;
use App\Controllers\Rh\RhContractRulesController;
use App\Controllers\Rh\RhValidationController;
use App\Controllers\Rh\RhExplicationController;
use App\Controllers\Rh\RhMissionController;
use App\Controllers\Rh\RhAttendanceController;
use App\Controllers\Rh\RhPayrollController;
use App\Controllers\Rh\RhPayrollEngineController;

/** @var Router $router */

$router->group('/rh', function (Router $router): void {
    $router->get('/', [RhDashboardController::class, 'index']);
    $router->get('/dashboard', [RhDashboardController::class, 'index']);
    $router->get('/mutations', [RhPersonnelController::class, 'mutationsIndex']);
    $router->get('/mouvements', [RhPersonnelController::class, 'movementsIndex']);
    
    // Pointage
    $router->get('/pointage', [RhAttendanceController::class, 'index']);
    $router->post('/pointage/journalier', [RhAttendanceController::class, 'storeDaily']);

    $router->get('/contrats', [RhLifecycleController::class, 'index']);

    $router->get('/cycle-vie', [RhLifecycleController::class, 'index']);
    $router->post('/cycle-vie/contrats', [RhLifecycleController::class, 'storeContract']);
    $router->post('/cycle-vie/missions', [RhLifecycleController::class, 'storeAssignment']);
    $router->post('/cycle-vie/evaluations', [RhLifecycleController::class, 'storeEvaluation']);
    $router->post('/cycle-vie/formations', [RhLifecycleController::class, 'storeTraining']);
    $router->post('/cycle-vie/discipline', [RhLifecycleController::class, 'storeDiscipline']);
    $router->post('/cycle-vie/workflows/{id}', [RhLifecycleController::class, 'decideWorkflow']);
    $router->post('/cycle-vie/demandes-employes/{id}', [RhLifecycleController::class, 'decideEmployeeRequest']);
    
    // Paie
    $router->get('/paie', [RhPayrollController::class, 'index']);
    $router->get('/paie/nouveau', [RhPayrollController::class, 'create']);
    $router->post('/paie/nouveau', [RhPayrollController::class, 'storeWizard']);
    $router->get('/paie/moteur', [RhPayrollEngineController::class, 'index']);
    $router->post('/paie/periodes', [RhPayrollController::class, 'storePeriod']);
    $router->post('/paie/variables', [RhPayrollController::class, 'storeVariables']);
    $router->post('/paie/calculer/{id}', [RhPayrollController::class, 'calculate']);
    $router->post('/paie/cloturer/{id}', [RhPayrollController::class, 'close']);

    $router->get('/parametrage', [RhSettingsController::class, 'index']);
    $router->post('/parametrage', [RhSettingsController::class, 'store']);
    $router->post('/parametrage/toggle', [RhSettingsController::class, 'toggle']);

    // Ordres de mission
    $router->get('/missions', [RhMissionController::class, 'index']);
    $router->get('/missions/nouveau', [RhMissionController::class, 'create']);
    $router->get('/missions/modifier/{id}', [RhMissionController::class, 'edit']);
    $router->post('/missions', [RhMissionController::class, 'store']);
    $router->post('/missions/decide/{id}', [RhMissionController::class, 'decide']);


    // Explications
    $router->get('/explications', [RhExplicationController::class, 'index']);
    $router->post('/explications', [RhExplicationController::class, 'store']);
    $router->post('/explications/respond/{id}', [RhExplicationController::class, 'respond']);
    $router->post('/explications/cloturer/{id}', [RhExplicationController::class, 'close']);
    $router->post('/explications/relancer/{id}', [RhExplicationController::class, 'relancer']);


    // Validations
    $router->get('/validations', [RhValidationController::class, 'index']);
    $router->post('/validations/decide/{id}', [RhValidationController::class, 'decideRequest']);
    $router->post('/validations/decide-workflow/{id}', [RhValidationController::class, 'decideWorkflow']);


    // Feries
    $router->get('/feries', [RhHolidayController::class, 'index']);
    $router->post('/feries', [RhHolidayController::class, 'store']);
    $router->post('/feries/toggle', [RhHolidayController::class, 'toggle']);

    // Signataires RH
    $router->get('/signataires', [RhSignatoryController::class, 'index']);
    $router->post('/signataires', [RhSignatoryController::class, 'store']);
    $router->post('/signataires/toggle', [RhSignatoryController::class, 'toggle']);

    // Regles contrats
    $router->get('/regles-contrats', [RhContractRulesController::class, 'index']);
    $router->post('/regles-contrats', [RhContractRulesController::class, 'store']);
    $router->post('/regles-contrats/toggle', [RhContractRulesController::class, 'toggle']);


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
