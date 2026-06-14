<?php

namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\Session;

class PermissionMiddleware
{
    public static function check(string $entityCode, string $action = 'view'): void
    {
        AuthMiddleware::check();
        if (Auth::can($entityCode, $action)) {
            return;
        }

        self::deny('Vous ne disposez pas de la permission requise pour cette action.');
    }

    public static function checkAll(array $requirements): void
    {
        AuthMiddleware::check();
        if (!Auth::canAll($requirements)) {
            self::deny('Vous ne disposez pas de tous les droits requis pour cette action.');
        }
    }

    public static function checkOperation(string $operation): void
    {
        AuthMiddleware::check();
        if (!Auth::canOperation($operation)) {
            self::deny('Vous ne disposez pas de tous les droits requis pour cette opération.');
        }
    }

    private static function deny(string $message): never
    {
        Session::flash('error', $message);
        $config = require BASE_PATH . '/config/app.php';
        header('Location: ' . rtrim($config['url'], '/') . '/selection_portail');
        exit;
    }
}
