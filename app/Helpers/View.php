<?php

namespace App\Helpers;

class View
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function asset(string $path): string
    {
        $config = require BASE_PATH . '/config/app.php';

        return rtrim($config['url'], '/') . '/public/assets/' . ltrim($path, '/');
    }

    public static function url(string $path = ''): string
    {
        $config = require BASE_PATH . '/config/app.php';

        return rtrim($config['url'], '/') . '/' . ltrim($path, '/');
    }

    public static function date(?string $date, string $format = 'd/m/Y'): string
    {
        if (empty($date)) {
            return '';
        }
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : self::e($date);
    }
}
