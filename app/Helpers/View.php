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

        // When accessed via a VirtualHost whose DocumentRoot is public/,
        // SCRIPT_NAME is /index.php → assets live at /assets/.
        // When accessed via localhost/ERP_LBP_REFACTO (DocumentRoot = www/),
        // SCRIPT_NAME is /ERP_LBP_REFACTO/index.php → assets live at
        // /ERP_LBP_REFACTO/public/assets/.
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $servesFromPublic = str_contains($scriptName, '/public/')
            || realpath($docRoot) === realpath(BASE_PATH . '/public');

        $prefix = $servesFromPublic ? '/assets/' : '/public/assets/';

        return rtrim($config['url'], '/') . $prefix . ltrim($path, '/');
    }

    public static function url(string $path = ''): string
    {
        $config = require BASE_PATH . '/config/app.php';

        return rtrim($config['url'], '/') . '/' . ltrim($path, '/');
    }
}
