<?php

namespace App\Helpers;

use App\Core\Auth as CoreAuth;

class Auth
{
    public static function setTokenPayload(array $payload): void
    {
        CoreAuth::setTokenPayload($payload);
    }

    public static function getTokenPayload(): ?array
    {
        return CoreAuth::getTokenPayload();
    }

    public static function id(): ?int
    {
        return CoreAuth::id();
    }

    public static function check(): bool
    {
        return CoreAuth::check();
    }

    public static function user(): ?array
    {
        return CoreAuth::user();
    }

    public static function getPermissions(): array
    {
        return CoreAuth::getPermissions();
    }

    public static function hasPermission(string ...$requiredPermissions): bool
    {
        return CoreAuth::hasPermission(...$requiredPermissions);
    }
}
