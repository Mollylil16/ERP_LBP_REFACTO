<?php

namespace App\Helpers;

use App\Models\Database;
use App\Models\User;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Admin\PermissionRepository;
use App\Security\PermissionAction;
use App\Services\Auth\AuthorizationService;

/**
 * Fournit des méthodes simples pour accéder à l’utilisateur connecté.
 */
class Auth
{
    private static ?int $cachedUserId = null;
    private static ?User $cachedUser = null;
    private static ?AuthorizationService $authorization = null;

    public static function id(): ?int
    {
        $userId = Session::get('auth_user_id');

        return $userId ? (int) $userId : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function user(): ?User
    {
        $userId = self::id();

        if (!$userId) {
            self::reset();
            return null;
        }

        if (self::$cachedUserId === $userId && self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $repository = new UserRepository(Database::getConnection());
        self::$cachedUserId = $userId;
        self::$cachedUser = $repository->findById($userId);
        self::$authorization = null;

        return self::$cachedUser;
    }

    public static function can(string $entityCode, string $action = PermissionAction::VIEW): bool
    {
        return self::authorization()->can($entityCode, $action);
    }

    public static function canAll(array $requirements): bool
    {
        return self::authorization()->canAll($requirements);
    }

    public static function canAny(array $requirements): bool
    {
        return self::authorization()->canAny($requirements);
    }

    public static function canOperation(string $operation): bool
    {
        return self::authorization()->canOperation($operation);
    }

    /**
     * Vérifie si l'utilisateur connecté possède un rôle particulier.
     */
    public static function hasRole(string $role): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if ($user->isAdmin) {
            return true;
        }
        return in_array($role, $user->roles, true);
    }

    /**
     * Vérifie si l'utilisateur possède au moins un des rôles spécifiés.
     */
    public static function hasAnyRole(array $roles): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if ($user->isAdmin) {
            return true;
        }
        foreach ($roles as $role) {
            if (in_array($role, $user->roles, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retourne l'identifiant de l'agence de l'utilisateur.
     */
    public static function agenceId(): ?int
    {
        $user = self::user();
        return $user ? $user->agenceId : null;
    }

    /**
     * Retourne l'identifiant de la zone régionale de l'utilisateur.
     */
    public static function zoneRegionaleId(): ?int
    {
        $user = self::user();
        return $user ? $user->zoneRegionaleId : null;
    }

    /**
     * Contrôle si l'utilisateur a le droit d'accéder aux données d'une agence cible.
     */
    public static function checkAgencyScope(?int $targetAgenceId): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if ($user->isAdmin) {
            return true;
        }

        // Les rôles globaux ont accès à toutes les agences
        $globalRoles = [
            'caissiere_principale',
            'superviseur_general',
            'assistant_dg',
            'dg',
            'agent_exploitation',
            'comptable'
        ];
        if (self::hasAnyRole($globalRoles)) {
            return true;
        }

        if ($targetAgenceId === null) {
            return false;
        }

        // Rôles locaux : restreints à leur agence propre
        $localRoles = ['agent_groupage', 'caissiere', 'chef_agence'];
        if (self::hasAnyRole($localRoles)) {
            return $user->agenceId === $targetAgenceId;
        }

        // Superviseur régional : limité aux agences de sa région
        if (self::hasRole('superviseur_regional')) {
            if ($user->zoneRegionaleId === null) {
                return false;
            }
            try {
                $db = Database::getConnection();
                $stmt = $db->prepare("SELECT zone_regionale_id FROM company_sites WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $targetAgenceId]);
                $siteRegionId = $stmt->fetchColumn();
                return $siteRegionId !== false && (int) $siteRegionId === $user->zoneRegionaleId;
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    public static function reset(): void
    {
        self::$cachedUserId = null;
        self::$cachedUser = null;
        self::$authorization = null;
    }

    private static function authorization(): AuthorizationService
    {
        self::user();
        self::$authorization ??= new AuthorizationService(
            self::$cachedUser,
            new PermissionRepository(Database::getConnection())
        );

        return self::$authorization;
    }
}
