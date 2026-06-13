<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';


define('BASE_PATH', dirname(__DIR__));

// Charger le fichier .env si présent
if (file_exists(BASE_PATH . '/.env')) {
    $lines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Autoloader PSR-4 adapté pour fichiers snake_case.php ou PascalCase.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $classPath = str_replace('\\', '/', $relativeClass);
    $snakePath = implode('/', array_map(function (string $segment): string {
        return strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $segment));
    }, explode('/', $classPath)));

    $prefixMap = [
        'Core/' => BASE_PATH . '/core/',
        'Modules/' => BASE_PATH . '/modules/',
        '' => BASE_PATH . '/app/',
    ];

    foreach ($prefixMap as $nsPrefix => $baseDir) {
        if ($nsPrefix === '' || str_starts_with($classPath, $nsPrefix)) {
            $relativePath = $nsPrefix === '' ? $classPath : substr($classPath, strlen($nsPrefix));
            $candidates = [
                $baseDir . $relativePath . '.php',
                $baseDir . implode('/', array_map(function (string $segment): string {
                    return strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $segment));
                }, explode('/', $relativePath))) . '.php',
            ];

            foreach ($candidates as $file) {
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        }
    }
});

use App\Models\Database;
use App\Database\MigrationRunner;

// Initialisation de la gestion globale des erreurs
\App\Core\ExceptionHandler::register();

// Initialiser la connexion PDO
$pdo = Database::getConnection();

// Lancer les migrations
$migrationRunner = new MigrationRunner($pdo);
$migrationRunner->run();
