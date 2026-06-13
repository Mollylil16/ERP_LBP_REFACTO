<?php

namespace App\Controllers;

class BaseController
{

    /**
     * Affiche une vue avec les données fournies.
     *
     * @param string $view Le chemin de la vue à afficher (ex: 'auth/login').
     * @param array $data Les données à extraire pour la vue.
     */
    protected function view(string $view, array $data = []): void
    {
        extract($data);

        require BASE_PATH . '/views/' . $view . '.php';
    }


    /**
     * Redirige vers une URL spécifique.
     *
     * @param string $path Le chemin relatif à rediriger (ex: '/dashboard').
     */
    protected function redirect(string $path): void
    {
        $config = require BASE_PATH . '/config/app.php';

        $baseUrl = rtrim($config['url'], '/');

        $path = '/' . ltrim($path, '/');

        header('Location: ' . $baseUrl . $path);
        exit;
    }


    /**
     * Redirige vers la page précédente.
     */
    protected function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';

        header('Location: ' . $referer);
        exit;
    }
}
