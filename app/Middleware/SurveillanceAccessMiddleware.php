<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Services\Shared\AuditLogService;
use App\Services\Shared\IntegrityRuleEngine;

/**
 * Middleware de sécurité pour le module de surveillance DG.
 * Enforce une séparation stricte des privilèges : même les administrateurs
 * système sont bloqués s'ils n'ont pas explicitement le rôle dg_surveillance.
 */
final class SurveillanceAccessMiddleware
{
    public static function check(): void
    {
        // 1. Vérifier la connexion active
        AuthMiddleware::check();

        $user = Auth::user();
        $route = $_SERVER['REQUEST_URI'] ?? '/surveillance';

        // 2. Vérifier strictement la présence du rôle dg_surveillance ou du statut d'administrateur
        $hasAccess = $user !== null && ($user->isAdmin || in_array('dg_surveillance', $user->roles, true));

        // 3. Loguer la tentative d'accès (Auto-audit)
        $auditId = AuditLogService::logSurveillanceAccess($route, $hasAccess);

        if (!$hasAccess) {
            // Déclencher une alerte d'intégrité de gravité TRÈS GRAVE
            if ($user !== null) {
                IntegrityRuleEngine::checkRule(
                    'ACCES_SURVEILLANCE_NON_AUTORISE',
                    (int) $user->id,
                    'surveillance_module',
                    0,
                    [
                        'route_tentee' => $route,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                    ],
                    $auditId
                );
            }

            // Refuser l'accès avec message d'erreur
            Session::flash('error', "Accès strictement interdit : ce module est réservé à la Surveillance DG.");
            
            // Rediriger vers la page d'accueil ou sélection portail
            $config = require BASE_PATH . '/config/app.php';
            $baseUrl = rtrim($config['url'], '/');
            header('Location: ' . $baseUrl . '/selection_portail');
            exit;
        }
    }
}
