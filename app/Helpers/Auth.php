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
