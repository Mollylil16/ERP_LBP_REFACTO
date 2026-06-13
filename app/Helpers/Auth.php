<?php

namespace App\Helpers;

use App\Models\Database;
use App\Models\User;
use App\Repositories\UserRepository;

/**
 * Fournit des méthodes simples pour accéder à l’utilisateur connecté.
 */
class Auth
{
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
            return null;
        }

        $repository = new UserRepository(Database::getConnection());

        return $repository->findById($userId);
    }
}
