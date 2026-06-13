<?php

use App\Router;

// Routes web minimalistes pour le backend / outils d'administration.
// Ce fichier est ici pour éviter les erreurs de chargement et peut être étendu ultérieurement.
$router->get('/web/health', function (): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'message' => 'Web backend operational']);
});
