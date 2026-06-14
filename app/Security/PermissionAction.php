<?php

namespace App\Security;

final class PermissionAction
{
    public const VIEW = 'view';
    public const CREATE = 'create';
    public const UPDATE = 'update';
    public const DELETE = 'delete';

    public static function all(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
        ];
    }

    public static function isValid(string $action): bool
    {
        return in_array($action, self::all(), true);
    }

    public static function column(string $action): ?string
    {
        return match ($action) {
            self::VIEW => 'can_view',
            self::CREATE => 'can_create',
            self::UPDATE => 'can_update',
            self::DELETE => 'can_delete',
            default => null,
        };
    }

    public static function normalize(array $rights): array
    {
        $normalized = [];
        foreach (self::all() as $action) {
            $normalized[$action] = !empty($rights[$action]);
        }

        if ($normalized[self::CREATE] || $normalized[self::UPDATE] || $normalized[self::DELETE]) {
            $normalized[self::VIEW] = true;
        }

        return $normalized;
    }
}
