<?php

namespace App\Core;

use App\Models\Database;

class Auth
{
    private static ?array $tokenPayload = null;
    private static ?array $user = null;

    public static function setTokenPayload(array $payload): void
    {
        self::$tokenPayload = $payload;
    }

    public static function getTokenPayload(): ?array
    {
        return self::$tokenPayload;
    }

    public static function id(): ?int
    {
        if (self::$tokenPayload !== null && isset(self::$tokenPayload['sub'])) {
            return (int) self::$tokenPayload['sub'];
        }

        return Session::get('auth_user_id') ? (int) Session::get('auth_user_id') : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $userId = self::id();
        if (!$userId) {
            return null;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT u.*, a.nom_agence, a.code_agence, a.telephone AS agence_telephone, r.name AS role_name
            FROM lbp_users u
            LEFT JOIN lbp_agences a ON u.id_agence = a.id
            LEFT JOIN lbp_roles r ON u.id_role = r.id
            WHERE u.id = :id
            LIMIT 1"
        );
        $stmt->execute(['id' => $userId]);

        $row = $stmt->fetch();
        if ($row) {
            self::$user = $row;
            return self::$user;
        }

        return null;
    }

    public static function getPermissions(): array
    {
        $user = self::user();
        if (!$user) {
            return [];
        }

        if (in_array($user['role_name'] ?? '', ['Directeur Général', 'SUPER_ADMIN'], true) || ($user['code_acces'] ?? 0) === 2) {
            return ['*'];
        }

        $pdo = Database::getConnection();
        // Fetch explicit user permissions AND permissions granted via the user's role
        $stmt = $pdo->prepare(
            "SELECT p.code
             FROM lbp_permissions p
             LEFT JOIN lbp_user_permissions up ON p.id = up.id_permission AND up.id_user = :id_user
             LEFT JOIN lbp_role_permissions rp ON p.id = rp.id_permission AND rp.id_role = :id_role
             WHERE up.id_user IS NOT NULL OR rp.id_role IS NOT NULL"
        );
        $stmt->execute([
            'id_user' => $user['id'],
            'id_role' => $user['id_role'] ?? 0
        ]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    public static function hasPermission(string ...$requiredPermissions): bool
    {
        $permissions = self::getPermissions();
        if (in_array('*', $permissions, true)) {
            return true;
        }

        foreach ($requiredPermissions as $permission) {
            if (in_array($permission, $permissions, true)) {
                return true;
            }
        }

        return false;
    }
}
