<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Finance\FinanceDashboardController;
use App\Controllers\Finance\FinanceController;

/** @var Router $router */

$router->group('/finance', function (Router $router): void {
    $router->get('/', [FinanceDashboardController::class, 'index']);
    $router->get('/dashboard', [FinanceDashboardController::class, 'index']);

    // Factures
    $router->get('/factures', [FinanceController::class, 'facturesIndex']);
    $router->get('/factures/nouveau', [FinanceController::class, 'factureCreate']);
    $router->post('/factures/enregistrer', [FinanceController::class, 'factureStore']);
    $router->get('/factures/{id}', [FinanceController::class, 'factureShow']);
    $router->post('/factures/{id}/encaisser', [FinanceController::class, 'factureEncaisser']);
    $router->post('/factures/{id}/relancer', [FinanceController::class, 'factureRelancer']);

    // Clôtures et Points de caisse
    $router->get('/clotures', [FinanceController::class, 'cloturesIndex']);
    $router->post('/clotures/soumettre', [FinanceController::class, 'clotureSoumettre']);
    $router->post('/clotures/{id}/consolider', [FinanceController::class, 'clotureConsolider']);

    // Dépenses prestataires
    $router->get('/depenses', [FinanceController::class, 'depensesIndex']);
    $router->post('/depenses/enregistrer', [FinanceController::class, 'depenseStore']);
    $router->post('/depenses/{id}/valider', [FinanceController::class, 'depenseValider']);

    // Comptabilité
    $router->get('/comptabilite', [FinanceController::class, 'comptabilite']);
});
