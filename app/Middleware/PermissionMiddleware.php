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


            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $request_uri = $_SERVER['REQUEST_URI'];

            $current_url = $protocol . $host . $request_uri;

            self::deny('Vous ne disposez pas de tous les droits requis pour cette opération. page actuelle ');
        }
    }

    private static function deny(string $message): never
    {
        Session::flash('error', $message);

        // 1. Vérifie si la page précédente existe dans l'historique du navigateur
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        // 2. Si le referer existe, on redirige dessus, sinon on retourne à la racine '/'
        $redirect_url = $referer ?: '/';

        header('Location: ' . $redirect_url);
        exit;
    }
}
