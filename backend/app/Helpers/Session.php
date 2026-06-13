<?php

namespace App\Helpers;

use App\Core\Session as CoreSession;

class Session
{
    public static function set(string $key, mixed $value): void
    {
        CoreSession::set($key, $value);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return CoreSession::get($key, $default);
    }

    public static function has(string $key): bool
    {
        return CoreSession::has($key);
    }

    public static function forget(string $key): void
    {
        CoreSession::forget($key);
    }

    public static function flash(string $key, string $message): void
    {
        CoreSession::flash($key, $message);
    }

    public static function getFlash(string $key): ?string
    {
        return CoreSession::getFlash($key);
    }
}
