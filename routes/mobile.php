<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Mobile\MobileAccesController;
use App\Controllers\Mobile\MobileAppController;
use App\Controllers\Mobile\MobilePushController;

/** @var Router $router */

/*
 * Application de direction (PWA).
 *
 * Le service worker et le manifeste sont servis par PHP plutôt que déposés dans
 * public/ : leur portée et leur URL de démarrage dépendent du répertoire dans
 * lequel l'ERP est installé, qui n'est pas connu à l'écriture des fichiers.
 */
$router->group('/mobile', function (Router $router): void {
    // Coque PWA
    $router->get('/manifest.webmanifest', [MobileAppController::class, 'manifeste']);
    $router->get('/sw.js', [MobileAppController::class, 'serviceWorker']);
    $router->get('/hors-ligne', [MobileAppController::class, 'horsLigne']);

    // Accès : connexion initiale, création puis saisie du code
    $router->get('/', [MobileAccesController::class, 'entree']);
    $router->get('/connexion', [MobileAccesController::class, 'connexion']);
    $router->post('/connexion', [MobileAccesController::class, 'connexionValider']);
    $router->get('/creer-code', [MobileAccesController::class, 'creerCode']);
    $router->post('/creer-code', [MobileAccesController::class, 'creerCodeValider']);
    $router->get('/verrouillage', [MobileAccesController::class, 'verrouillage']);
    $router->post('/verrouillage', [MobileAccesController::class, 'deverrouiller']);
    $router->post('/verrouiller', [MobileAccesController::class, 'verrouiller']);
    $router->post('/oublier-appareil', [MobileAccesController::class, 'oublierAppareil']);
    $router->get('/installation', [MobileAccesController::class, 'installation']);

    // Écrans
    $router->get('/tableau-de-bord', [MobileAppController::class, 'tableauDeBord']);
    $router->get('/validations', [MobileAppController::class, 'validations']);
    $router->get('/personnel', [MobileAppController::class, 'personnel']);
    $router->get('/anomalies', [MobileAppController::class, 'anomalies']);
    $router->post('/anomalies/traiter', [MobileAppController::class, 'traiterSignalement']);
    $router->get('/reglages', [MobileAppController::class, 'reglages']);

    // Décisions prises depuis le téléphone : délèguent à la logique métier existante
    $router->post('/validations/workflow/{id}', [MobileAppController::class, 'deciderWorkflow']);
    $router->post('/validations/demande/{id}', [MobileAppController::class, 'deciderDemande']);

    // Notifications
    $router->get('/push/cle-publique', [MobilePushController::class, 'clePublique']);
    $router->post('/push/abonner', [MobilePushController::class, 'abonner']);
    $router->post('/push/desabonner', [MobilePushController::class, 'desabonner']);
    $router->post('/push/test', [MobilePushController::class, 'test']);
});
