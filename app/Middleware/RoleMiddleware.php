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
     * La direction RH, sur un écran du module Finance.
     *
     * L'adresse demandée suffit à décider : tout ce qui vit sous /finance lui
     * est ouvert, hormis le contrôle des caisses.
     */
    private static function estLaDirectionRhDansFinance(): bool
    {
        if (!Auth::hasAnyRole(['responsable_rh'])) {
            return false;
        }

        $chemin = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        return str_contains($chemin, '/finance')
            && !str_contains($chemin, '/finance/controle-caisse');
    }

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

        // La direction RH a l'accès complet au module Finance, décidé par la
        // direction le 01/10/2026. Le module compte quarante et un contrôles de
        // rôles : l'y ajouter un par un l'aurait oubliée au premier écran
        // suivant. La règle est donc posée une fois, ici.
        //
        // Une exception : la surveillance des caisses reste au directeur et à
        // l'administrateur. L'écran dit ce que la direction contrôle et quand ;
        // il avait été fermé pour cela, même au comptable.
        if (self::estLaDirectionRhDansFinance()) {
            return;
        }

        // L'Assistant DG possède les mêmes accès de consultation globale que le DG et l'Admin
        if (Auth::isAssistantDg()) {
            if (in_array('dg', $allowedRoles, true) || in_array('admin', $allowedRoles, true) || in_array('assistant_dg', $allowedRoles, true) || in_array('assistante_dg', $allowedRoles, true)) {
                return;
            }
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
            if (PHP_SAPI !== 'cli') {
                exit;
            }
        }
    }
}
