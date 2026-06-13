<?php

use App\Router;
use App\Modules\Logistique\Controllers\ExpeditionController;

$router->get('/api/logistique/expeditions', [ExpeditionController::class, 'listExpeditions']);
$router->get('/api/logistique/expeditions/:id', [ExpeditionController::class, 'getExpedition']);
$router->post('/api/logistique/expeditions', [ExpeditionController::class, 'createExpedition']);
$router->patch('/api/logistique/expeditions/:id/statut', [ExpeditionController::class, 'updateStatut']);
