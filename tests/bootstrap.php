<?php

declare(strict_types=1);

/**
 * Bootstrap de tests ERP LBP.
 *
 * Important :
 * - Ne charge pas bootstrap/app.php, car celui-ci démarre les migrations et le seeder.
 * - Charge uniquement l'autoloader applicatif nécessaire aux tests unitaires/feature.
 * - Les smoke tests, eux, chargent bootstrap/app.php volontairement.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/TestCase.php';
