<?php

use App\Router;
use App\Modules\Finance\Controllers\FactureController;
use App\Modules\Finance\Controllers\PaiementController;
use App\Modules\Finance\Controllers\PointCaisseController;

$router->get('/api/finance', [FactureController::class, 'index']);

// Factures
$router->get('/api/finance/factures', [FactureController::class, 'listFactures']);
$router->get('/api/finance/factures/:id', [FactureController::class, 'getFacture']);
$router->post('/api/finance/factures', [FactureController::class, 'createFacture']);

// Paiements
$router->get('/api/finance/paiements', [PaiementController::class, 'listPaiements']);
$router->get('/api/finance/paiements/:id', [PaiementController::class, 'getPaiement']);
$router->post('/api/finance/paiements', [PaiementController::class, 'createPaiement']);

// Points de Caisse (Clôture journalière)
$router->get('/api/finance/points-caisse', [PointCaisseController::class, 'listPoints']);
$router->post('/api/finance/points-caisse', [PointCaisseController::class, 'createPoint']);
$router->patch('/api/finance/points-caisse/:id/valider', [PointCaisseController::class, 'validerPoint']);

// Suivi Caisse (Mouvements de Caisse)
$router->get('/api/finance/caisse', [\App\Modules\Finance\Controllers\CaisseController::class, 'getStatus']);
$router->get('/api/finance/caisse/mouvements', [\App\Modules\Finance\Controllers\CaisseController::class, 'listMouvements']);
$router->post('/api/finance/caisse/appro', [\App\Modules\Finance\Controllers\CaisseController::class, 'addApprovisionnement']);
$router->post('/api/finance/caisse/decaissement', [\App\Modules\Finance\Controllers\CaisseController::class, 'addDecaissement']);
$router->post('/api/finance/caisse/entree', [\App\Modules\Finance\Controllers\CaisseController::class, 'addEntree']);
