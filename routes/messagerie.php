<?php

declare(strict_types=1);

use App\Controllers\Messagerie\MessagerieController;
use App\Router;

/** @var Router $router */

/*
 * Messagerie interne (lot 1 : socle). Toutes les routes exigent une session
 * active ; toutes les écritures sont des POST avec jeton CSRF. Les réponses
 * de /api sont en JSON : { "ok": true, ... } ou { "ok": false, "message": "..." }.
 */
$router->group('/messagerie', function (Router $router): void {
    $router->get('/', [MessagerieController::class, 'index']);

    $router->group('/api', function (Router $router): void {
        // Relevé périodique (toutes les 3 s)
        $router->get('/nouveautes', [MessagerieController::class, 'nouveautes']);

        // Discussions
        $router->get('/conversations', [MessagerieController::class, 'conversations']);
        $router->get('/conversations/{id}', [MessagerieController::class, 'detail']);
        $router->get('/conversations/{id}/messages', [MessagerieController::class, 'messages']);
        $router->post('/conversations/{id}/messages', [MessagerieController::class, 'envoyer']);
        $router->post('/conversations/{id}/lu', [MessagerieController::class, 'marquerLu']);
        $router->post('/conversations/{id}/epingler', [MessagerieController::class, 'epingler']);
        $router->post('/conversations/{id}/desepingler', [MessagerieController::class, 'desepingler']);
        $router->post('/conversations/{id}/sourdine', [MessagerieController::class, 'sourdine']);
        $router->post('/conversations/{id}/archiver', [MessagerieController::class, 'archiver']);

        // Création
        $router->post('/directes', [MessagerieController::class, 'ouvrirDirecte']);
        $router->post('/groupes', [MessagerieController::class, 'creerGroupe']);

        // Administration d'un groupe
        $router->post('/conversations/{id}/membres/ajouter', [MessagerieController::class, 'ajouterMembres']);
        $router->post('/conversations/{id}/membres/retirer', [MessagerieController::class, 'retirerMembre']);
        $router->post('/conversations/{id}/membres/role', [MessagerieController::class, 'changerRole']);
        $router->post('/conversations/{id}/quitter', [MessagerieController::class, 'quitter']);
        $router->post('/conversations/{id}/renommer', [MessagerieController::class, 'renommer']);
        $router->post('/conversations/{id}/annonces', [MessagerieController::class, 'annonces']);

        // Messages
        $router->post('/messages/{id}/modifier', [MessagerieController::class, 'modifier']);
        $router->post('/messages/{id}/supprimer', [MessagerieController::class, 'supprimer']);
        $router->post('/messages/{id}/transferer', [MessagerieController::class, 'transferer']);
        $router->get('/messages/{id}/lu-par', [MessagerieController::class, 'luPar']);

        // Recherche, annuaire, préférences
        $router->get('/recherche', [MessagerieController::class, 'recherche']);
        $router->get('/annuaire', [MessagerieController::class, 'annuaire']);
        $router->get('/preferences', [MessagerieController::class, 'preferences']);
        $router->post('/preferences', [MessagerieController::class, 'enregistrerPreferences']);
    });
});
