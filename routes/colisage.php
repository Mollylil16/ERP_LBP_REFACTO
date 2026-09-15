<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Colisage\ColisageDashboardController;
use App\Controllers\Colisage\ColisageController;
use App\Controllers\Colisage\ColisageAutresController;
use App\Controllers\Colisage\ColisageDhlController;
use App\Controllers\Colisage\ExploitationController;
use App\Controllers\Colisage\RapportsController;

/** @var Router $router */

// Sous-menus Opération à trajet verrouillé (Groupage Cargo, Colis Rapide, DHL) :
// exactement le même formulaire/flux que la saisie générique de colis, avec le
// trajet imposé par le sous-menu emprunté et non modifiable par l'agent (Règle 3.4).
$router->get('/operation/{code}/saisir', [ColisageController::class, 'create']);

$router->group('/colisage', function (Router $router): void {
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
    $router->get('/parcels/{id}/modifier', [ColisageController::class, 'editParcel']);
    $router->post('/parcels/{id}/modifier', [ColisageController::class, 'updateParcel']);
    $router->post('/parcels/{id}/supprimer', [ColisageController::class, 'deleteParcel']);
    $router->post('/parcels/{id}/statut-depart', [ColisageController::class, 'updateStatutDepart']);

    // Scan Express 2-Scans (Départ / Arrivée)
    $router->get('/scan-express', [ColisageController::class, 'expressScanPage']);
    $router->post('/scan-express/process', [ColisageController::class, 'processExpressScan']);

    // Pointage des colis : l'agence d'envoi marque ses départs, l'agence
    // d'arrivée coche ce qu'elle reçoit, la direction suit l'ensemble.
    $router->get('/departs', [\App\Controllers\Colisage\PointageColisController::class, 'departs']);
    $router->post('/departs/marquer-partis', [\App\Controllers\Colisage\PointageColisController::class, 'marquerPartis']);
    $router->get('/departs/{id}', [\App\Controllers\Colisage\PointageColisController::class, 'detail']);
    $router->get('/reception', [\App\Controllers\Colisage\PointageColisController::class, 'reception']);
    $router->post('/reception/pointer', [\App\Controllers\Colisage\PointageColisController::class, 'pointer']);
    $router->post('/reception/scanner', [\App\Controllers\Colisage\PointageColisController::class, 'scanner']);
    $router->post('/reception/{id}/tout-pointer', [\App\Controllers\Colisage\PointageColisController::class, 'toutPointer']);
    $router->post('/reception/{id}/prevenir-clients', [\App\Controllers\Colisage\PointageColisController::class, 'prevenirClients']);
    $router->get('/suivi-departs', [\App\Controllers\Colisage\PointageColisController::class, 'suivi']);

    // Dossiers d'envoi : l'agent export tient le transport, les coûts et les
    // pièces de chaque envoi ; le Directeur général valide. Les chemins fixes
    // précèdent /envois/{id}, que le routeur essaierait sinon en premier.
    $router->get('/envois', [\App\Controllers\Colisage\DossierEnvoiController::class, 'liste']);
    $router->get('/envois/nouveau', [\App\Controllers\Colisage\DossierEnvoiController::class, 'nouveau']);
    $router->post('/envois/enregistrer', [\App\Controllers\Colisage\DossierEnvoiController::class, 'enregistrer']);
    $router->get('/envois/a-valider', [\App\Controllers\Colisage\DossierEnvoiController::class, 'aValider']);
    $router->get('/envois/pieces-manquantes', [\App\Controllers\Colisage\DossierEnvoiController::class, 'piecesManquantes']);
    $router->get('/envois/historique', [\App\Controllers\Colisage\DossierEnvoiController::class, 'historique']);
    $router->get('/envois/historique/pdf', [\App\Controllers\Colisage\DossierEnvoiController::class, 'historiquePdf']);
    $router->get('/envois/historique/excel', [\App\Controllers\Colisage\DossierEnvoiController::class, 'historiqueExcel']);
    $router->get('/envois/prestataires', [\App\Controllers\Colisage\DossierEnvoiController::class, 'prestataires']);
    $router->post('/envois/prestataires/enregistrer', [\App\Controllers\Colisage\DossierEnvoiController::class, 'enregistrerPrestataire']);
    $router->get('/envois/{id}', [\App\Controllers\Colisage\DossierEnvoiController::class, 'fiche']);
    $router->get('/envois/{id}/modifier', [\App\Controllers\Colisage\DossierEnvoiController::class, 'modifier']);
    $router->post('/envois/{id}/modifier', [\App\Controllers\Colisage\DossierEnvoiController::class, 'mettreAJour']);
    $router->get('/envois/{id}/pdf', [\App\Controllers\Colisage\DossierEnvoiController::class, 'fichePdf']);
    $router->post('/envois/{id}/documents', [\App\Controllers\Colisage\DossierEnvoiController::class, 'deposerDocument']);
    $router->get('/envois/{id}/documents/{document}', [\App\Controllers\Colisage\DossierEnvoiController::class, 'telechargerDocument']);
    $router->post('/envois/{id}/documents/{document}/retirer', [\App\Controllers\Colisage\DossierEnvoiController::class, 'retirerDocument']);
    $router->post('/envois/{id}/soumettre', [\App\Controllers\Colisage\DossierEnvoiController::class, 'soumettre']);
    $router->post('/envois/{id}/valider', [\App\Controllers\Colisage\DossierEnvoiController::class, 'valider']);
    $router->post('/envois/{id}/renvoyer', [\App\Controllers\Colisage\DossierEnvoiController::class, 'renvoyer']);
    $router->post('/envois/{id}/rouvrir', [\App\Controllers\Colisage\DossierEnvoiController::class, 'rouvrir']);
    $router->post('/envois/{id}/reaffecter', [\App\Controllers\Colisage\DossierEnvoiController::class, 'reaffecter']);
    $router->post('/envois/{id}/annuler', [\App\Controllers\Colisage\DossierEnvoiController::class, 'annuler']);

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

    // Suivi & Rentabilité DHL Express
    $router->get('/dhl', [ColisageDhlController::class, 'index']);
    $router->get('/dhl/rentabilite', [ColisageDhlController::class, 'index']);
    $router->get('/dhl/export-csv', [ColisageDhlController::class, 'exportCsv']);

    $router->get('/documents', [ColisageController::class, 'documents']);
    $router->get('/reporting', [ColisageController::class, 'reporting']);
    $router->get('/guide', [ColisageController::class, 'userGuide']);

    // Rapports journaliers / mensuels par agence
    $router->get('/rapports', [RapportsController::class, 'journalier']);
    $router->get('/rapports/mensuel', [RapportsController::class, 'mensuel']);
    $router->get('/rapports/export-csv', [RapportsController::class, 'exportCsv']);
    $router->get('/rapports/export-pdf', [RapportsController::class, 'exportPdf']);

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

