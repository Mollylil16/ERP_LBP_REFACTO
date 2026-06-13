<?php

namespace App\Middleware;

use App\Helpers\Session;

/**
 * Empêche un utilisateur déjà connecté d’accéder aux pages invité.
 *
 * Exemple : login, register.
 */
class GuestMiddleware
{
    public static function check(): void
    {
        if (Session::has('auth_user_id')) {
            self::redirect('/dashboard');
        }
    }

    private static function redirect(string $path): void
    {
        $config = require BASE_PATH . '/config/app.php';

        header('Location: ' . rtrim($config['url'], '/') . '/' . ltrim($path, '/'));
        exit;
    }
}
