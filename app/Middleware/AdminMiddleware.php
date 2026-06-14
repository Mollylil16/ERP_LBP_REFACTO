<?php

namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\Session;

class AdminMiddleware
{
    public static function check(): void
    {
        AuthMiddleware::check();
        $user = Auth::user();

        if (!$user || !$user->isAdmin || $user->status !== 'active') {
            Session::flash('error', 'Cet espace est réservé aux administrateurs.');
            self::redirect('/selection_portail');
        }
    }

    private static function redirect(string $path): void
    {
        $config = require BASE_PATH . '/config/app.php';
        header('Location: ' . rtrim($config['url'], '/') . '/' . ltrim($path, '/'));
        exit;
    }
}
