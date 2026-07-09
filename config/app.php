<?php

return [
    'name' => 'ERP LBP Transit',
    'tagline' => 'Plateforme de gestion des opérations de transit et de logistique.',
    'url' => (function() {
        $appUrl = $_SERVER['APP_URL'] ?? $_ENV['APP_URL'] ?? getenv('APP_URL');
        if ($appUrl !== false && $appUrl !== null && $appUrl !== '') {
            return rtrim((string)$appUrl, '/');
        }
        
        $scheme = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(dirname($scriptName), '/\\');
        
        if (str_ends_with($baseDir, '/public') || str_ends_with($baseDir, '\\public')) {
            $baseDir = substr($baseDir, 0, -7);
        }
        
        return rtrim($scheme . '://' . $host . $baseDir, '/');
    })(),

    'theme' => [
        'primary' => '#1d2b57',
        'secondary' => '#fabd02',
    ],
];
