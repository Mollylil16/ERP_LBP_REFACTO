<?php

namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\Session;

/**
 * Middleware pour le contrôle d'accès basé sur les rôles (RBAC).
 */
class RoleMiddleware
{
    /**
     * Vérifie si l'utilisateur possède au moins un des rôles autorisés.
     *
     * @param array<string> $allowedRoles
     */
    public static function check(array $allowedRoles): void
    {
        // 1. Vérification de l'authentification et de l'état actif du compte
        AuthMiddleware::check();

        $user = Auth::user();
        if ($user && $user->isAdmin) {
            return; // L'administrateur système passe toutes les barrières
        }

        // 2. Vérification des rôles
        if (!Auth::hasAnyRole($allowedRoles)) {
            Session::flash('error', "Accès refusé : Vous n'avez pas l'habilitation requise pour cette page.");
            
            // Redirection sécurisée vers la page de sélection du portail ou précédente
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            $config = require BASE_PATH . '/config/app.php';
            $baseUrl = rtrim($config['url'], '/');

            if ($referer !== '' && str_starts_with($referer, $baseUrl)) {
                header('Location: ' . $referer);
            } else {
                header('Location: ' . $baseUrl . '/selection_portail');
            }
            exit;
        }
    }
}
