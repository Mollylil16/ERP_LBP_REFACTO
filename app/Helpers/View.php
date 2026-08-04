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
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

        $servesFromPublic = str_contains($scriptName, '/public/')
            || (realpath($docRoot) && realpath(BASE_PATH . '/public') && realpath($docRoot) === realpath(BASE_PATH . '/public'));

        $baseDir = rtrim(dirname($scriptName), '/\\');
        if (str_ends_with($baseDir, '/public') || str_ends_with($baseDir, '\\public')) {
            $baseDir = substr($baseDir, 0, -7);
        }

        $prefix = $servesFromPublic ? ($baseDir . '/assets/') : ($baseDir . '/public/assets/');

        $url = '/' . ltrim($prefix . ltrim($path, '/'), '/');

        $filePath = BASE_PATH . '/public/assets/' . ltrim($path, '/');
        if (is_file($filePath)) {
            $url .= '?v=' . filemtime($filePath);
        }

        return $url;
    }

    public static function url(string $path = ''): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(dirname($scriptName), '/\\');
        if (str_ends_with($baseDir, '/public') || str_ends_with($baseDir, '\\public')) {
            $baseDir = substr($baseDir, 0, -7);
        }

        $target = '/' . ltrim($baseDir . '/' . ltrim($path, '/'), '/');
        return $target === '//' ? '/' : $target;
    }

    /**
     * @return array<int, string>
     */
    public static function monthNames(): array
    {
        return [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
    }
}
