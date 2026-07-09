<?php

namespace App\Helpers;

class View
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function asset(string $path): string
    {
        $config = require BASE_PATH . '/config/app.php';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $hasPublic = str_contains($scriptName, '/public/');
        $prefix = $hasPublic ? '/public/assets/' : '/assets/';

        return rtrim($config['url'], '/') . $prefix . ltrim($path, '/');
    }

    public static function url(string $path = ''): string
    {
        $config = require BASE_PATH . '/config/app.php';

        return rtrim($config['url'], '/') . '/' . ltrim($path, '/');
    }
}
