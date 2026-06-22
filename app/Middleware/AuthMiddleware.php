<?php

namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\Session;

/**
 * Vérifie que l’utilisateur est connecté.
 *
 * Ce middleware protège les pages privées comme le dashboard,
 * les autres pages d’administration, etc. Il doit être appelé au début de la méthode du contrôleur
 * qui gère la route protégée.
 */
class AuthMiddleware
{
    public static function check(): void
    {
        if (!Session::has('auth_user_id')) {
            Session::flash('error', 'Veuillez vous connecter pour accéder à cette page.');

            self::redirect('/login');
        }

        $user = Auth::user();
        if (!$user || $user->status !== 'active') {
            Session::forget('auth_user_id');
            Session::flash('error', 'Votre compte est désactivé. Contactez un administrateur.');
            self::redirect('/login');
        }
    }

    private static function redirect(string $path): void
    {
        $config = require BASE_PATH . '/config/app.php';

        header('Location: ' . rtrim($config['url'], '/') . '/' . ltrim($path, '/'));
        exit;
    }
}
