<?php

use App\Router;
use App\Modules\Colissage\Controllers\ColisController;
use App\Modules\Colissage\Controllers\ClientController;
use App\Modules\Colissage\Controllers\InventaireController;

$router->get('/api/colissage', [ColisController::class, 'index']);

// Colis
$router->get('/api/colissage/colis', [ColisController::class, 'listColis']);
$router->get('/api/colissage/colis/:id', [ColisController::class, 'getColis']);
$router->post('/api/colissage/colis', [ColisController::class, 'createColis']);
$router->patch('/api/colissage/colis/:id/statut', [ColisController::class, 'updateStatut']);
$router->post('/api/colissage/colis/:id/retrait', [ColisController::class, 'retraitColis']);

// Inventaires
$router->get('/api/colissage/inventaires', [InventaireController::class, 'listInventaires']);
$router->post('/api/colissage/inventaires', [InventaireController::class, 'createInventaire']);

// Expéditions (Cartographie Yango)
$router->get('/api/colissage/expeditions', [\App\Modules\Colissage\Controllers\ExpeditionController::class, 'listExpeditions']);
$router->post('/api/colissage/expeditions', [\App\Modules\Colissage\Controllers\ExpeditionController::class, 'createExpedition']);
$router->get('/api/colissage/expeditions/:id/tracking', [\App\Modules\Colissage\Controllers\ExpeditionController::class, 'getGpsTracking']);
$router->post('/api/colissage/expeditions/:id/tracking', [\App\Modules\Colissage\Controllers\ExpeditionController::class, 'addGpsTracking']);

// Clients
$router->get('/api/colissage/clients', [ClientController::class, 'listClients']);
$router->get('/api/colissage/clients/:id', [ClientController::class, 'getClient']);
$router->post('/api/colissage/clients', [ClientController::class, 'createClient']);
