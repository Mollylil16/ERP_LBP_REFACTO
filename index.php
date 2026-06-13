<?php


/**
 * @var mixed $_SERVER
 * @var mixed $_SESSION
 * @var string $basePath
 * @var string $requestedPath
 * @var string $scriptBase
 * @var string[] $homePaths
 * Ce fichier est le point d'entrée de l'application. 
 * Il gère les redirections vers les différentes pages en fonction de l'URL demandée 
 * et de l'état de la session utilisateur.
 * Il doit rester simple et ne pas contenir de logique métier ou de rendu HTML.
 * Toute la logique de contrôle d'accès doit être gérée dans les contrôleurs et middleware dédiés.
 * Il est important de ne pas ajouter de code de traitement ou de rendu ici pour maintenir 
 * une architecture propre et modulaire.
 */
$requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptBase = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = rtrim($scriptBase, '/');

$homePaths = ['/', $basePath . '/', $basePath];

if (in_array($requestedPath, $homePaths, true)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($_SESSION['auth_user_id'])) {
        $_SERVER['REQUEST_URI'] = '/selection_portail.php';
    } else {
        $_SERVER['REQUEST_URI'] = '/login';
    }
}

require_once __DIR__ . '/public/index.php';
