<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Colisage\ColisageDashboardController;
use App\Controllers\Colisage\ColisageController;
use App\Controllers\Colisage\ColisageAutresController;
use App\Controllers\Colisage\ExploitationController;
use App\Controllers\Colisage\RapportsController;
use App\Controllers\Colisage\OperationSaisieController;

/** @var Router $router */

$router->get('/operation/{code}/saisir', [OperationSaisieController::class, 'create']);
$router->post('/operation/enregistrer', [OperationSaisieController::class, 'store']);

$router->group('/colisage', function (Router $router): void {
    // Saisie par trajet dans le groupe /colisage
    $router->get('/operation/{code}/saisir', [OperationSaisieController::class, 'create']);
    $router->post('/operation/enregistrer', [OperationSaisieController::class, 'store']);
    $router->get('/', [ColisageDashboardController::class, 'index']);
    $router->get('/dashboard', [ColisageDashboardController::class, 'index']);

    $router->get('/parcels', [ColisageController::class, 'index']);
    $router->get('/parcels/nouveau', [ColisageController::class, 'create']);
    $router->post('/parcels/enregistrer', [ColisageController::class, 'store']);
    $router->get('/parcels/{id}', [ColisageController::class, 'show']);
    $router->get('/parcels/{id}/facture', [ColisageController::class, 'printInvoice']);
    $router->post('/parcels/{id}/facturer', [ColisageController::class, 'autoFacturer']);
    $router->get('/parcels/{id}/etiquette', [ColisageController::class, 'printLabel']);
    $router->post('/parcels/{id}/retirer', [ColisageController::class, 'withdraw']);
    $router->post('/parcels/{id}/transferer', [ColisageController::class, 'transfer']);

    $router->get('/groupage', [ColisageController::class, 'groupageIndex']);
    $router->get('/groupage/nouveau', [ColisageController::class, 'groupageCreate']);
    $router->post('/groupage/enregistrer', [ColisageController::class, 'groupageStore']);
    $router->get('/groupage/{id}', [ColisageController::class, 'groupageShow']);
    $router->get('/groupage/{id}/manifeste', [ColisageController::class, 'groupagePrintManifest']);
    $router->post('/groupage/{id}/colis', [ColisageController::class, 'groupageAddParcel']);
    $router->post('/groupage/{id}/demarrer', [ColisageController::class, 'groupageStart']);
    $router->post('/groupage/{id}/arriver', [ColisageController::class, 'groupageArrive']);

    $router->get('/autres', [ColisageAutresController::class, 'index']);
    $router->get('/autres/nouveau', [ColisageAutresController::class, 'create']);
    $router->post('/autres/enregistrer', [ColisageAutresController::class, 'store']);

    $router->get('/documents', [ColisageController::class, 'documents']);
    $router->get('/reporting', [ColisageController::class, 'reporting']);

    // Rapports journaliers / mensuels par agence
    $router->get('/rapports', [RapportsController::class, 'journalier']);
    $router->get('/rapports/mensuel', [RapportsController::class, 'mensuel']);
    $router->get('/rapports/export-csv', [RapportsController::class, 'exportCsv']);

    $router->get('/settings', [ColisageController::class, 'settings']);
    $router->post('/settings/enregistrer', [ColisageController::class, 'saveSettings']);

    // Filtre & Recherche (Facturation)
    $router->get('/filtre', [\App\Controllers\Facturation\FacturationFilterController::class, 'index']);
    $router->get('/filtre/export-pdf', [\App\Controllers\Facturation\FacturationFilterController::class, 'exportPdf']);
    $router->get('/filtre/export-excel', [\App\Controllers\Facturation\FacturationFilterController::class, 'exportExcel']);

    // Exploitation module routes (Web and API compatibility)
    $router->get('/exploitation/synthese', [ExploitationController::class, 'synthese']);
    $router->get('/exploitation/tracking', [ExploitationController::class, 'tracking']);
    $router->post('/exploitation/tracking/{id}', [ExploitationController::class, 'addGpsTracking']);
    $router->get('/exploitation/credits', [ExploitationController::class, 'credits']);
    $router->post('/exploitation/credits/declarer', [ExploitationController::class, 'soumettreCredit']);
    $router->post('/exploitation/credits/{id}/regler', [ExploitationController::class, 'reglerCredit']);
    $router->get('/exploitation/fournitures', [ExploitationController::class, 'fournitures']);
    $router->post('/exploitation/fournitures/demander', [ExploitationController::class, 'soumettreDemande']);
    $router->post('/exploitation/fournitures/{id}/statut', [ExploitationController::class, 'updateFournituresStatus']);
});

// API routes mapped for external/mobile integration compatibilities
$router->get('/api/exploitation/synthese', [ExploitationController::class, 'synthese']);
$router->post('/api/expeditions/{id}/tracking', [ExploitationController::class, 'addGpsTracking']);
$router->get('/api/fournitures', [ExploitationController::class, 'fournitures']);
$router->post('/api/fournitures/{id}/statut', [ExploitationController::class, 'updateFournituresStatus']);
$router->get('/api/credits/inter-agences', [ExploitationController::class, 'credits']);
$router->post('/api/credits/inter-agences/{id}/regler', [ExploitationController::class, 'reglerCredit']);

