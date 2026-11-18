<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Accès donnés à une personne au-delà de son métier (décidé le 18/11/2026).
 *
 * - ROLE_FINANCE : tout le module Finance, comme la direction RH depuis le
 *   01/10/2026 — sauf le contrôle des caisses, réservé au DG et à
 *   l'administrateur. Donné à Soro Ibrahim.
 * - ROLE_TOUS_MODULES : tous les modules métier, sauf l'Administration, le
 *   Pilotage DG, le module RH réservé, et tout écran que son module réserve
 *   au seul DG (contrôle des caisses, écrans DG du call center…). Donné à la
 *   responsable RH.
 *
 * Les deux rôles s'attribuent dans Administration > Utilisateurs, comme les
 * autres ; c'est ici seulement que se décide ce qu'ils ouvrent.
 */
final class AccesEtendu
{
    public const ROLE_FINANCE = 'acces_finance';
    public const ROLE_TOUS_MODULES = 'acces_tous_modules';

    /** Rôles de direction : une page ouverte à eux seuls reste à eux seuls. */
    private const DIRECTION = ['dg', 'admin', 'dg_surveillance', 'assistant_dg', 'assistante_dg'];

    /** Chemins jamais ouverts par ces rôles. */
    private const EXCLUS = ['/admin', '/pilotage-dg', '/rh', '/finance/controle-caisse', '/messagerie/direction'];

    public static function tousModules(): bool
    {
        return Auth::hasRole(self::ROLE_TOUS_MODULES);
    }

    /**
     * La page demandée, gardée par RoleMiddleware avec ces rôles, s'ouvre-t-elle
     * à l'utilisateur courant grâce à un accès étendu ?
     *
     * @param array<int, string> $rolesDeLaPage
     */
    public static function ouvre(string $chemin, array $rolesDeLaPage): bool
    {
        $chemin = self::sansPrefixe($chemin);
        foreach (self::EXCLUS as $exclu) {
            if ($chemin === $exclu || str_starts_with($chemin, $exclu . '/')) {
                return false;
            }
        }

        if (Auth::hasRole(self::ROLE_FINANCE) && ($chemin === '/finance' || str_starts_with($chemin, '/finance/'))) {
            return true;
        }

        if (!self::tousModules()) {
            return false;
        }

        // Une page que son module réserve à la direction reste à la direction.
        return array_diff($rolesDeLaPage, self::DIRECTION) !== [];
    }

    /** Le chemin sans le dossier d'installation local (/ERP_LBP_REFACTO). */
    private static function sansPrefixe(string $chemin): string
    {
        $chemin = '/' . ltrim($chemin, '/');
        $pos = strpos($chemin, '/', 1);
        if ($pos !== false && str_starts_with(strtolower($chemin), '/erp_lbp_refacto/')) {
            $chemin = substr($chemin, $pos);
        }

        return rtrim($chemin, '/') ?: '/';
    }
}
