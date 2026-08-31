<?php

declare(strict_types=1);

// Configuration du fuseau horaire par défaut (GMT pour la Côte d'Ivoire)
date_default_timezone_set('Africa/Abidjan');

use App\Database\MigrationRunner;
use App\Models\Database;
use App\Repositories\Admin\UserRepository;
use App\Services\Admin\AdminSeederService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

spl_autoload_register(function (string $class): void {

    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));

    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

try {
    $pdo = Database::getConnection();

    $migrationRunner = new MigrationRunner($pdo);
    $migrationRunner->run();

    $adminSeeder = new AdminSeederService(new UserRepository($pdo));
    $adminSeeder->seed();
} catch (\Throwable $e) {
    error_log('[ERP LBP Bootstrap Warning] ' . $e->getMessage());
}
