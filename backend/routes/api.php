<?php

use App\Router;

// Routes API centrales
// Chaque module peut exposer ses routes en ajoutant son propre fichier de routes.
$router->get('/api/health', function (): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'message' => 'API backend operational']);
});

// Modules
$moduleRoutes = [
    BASE_PATH . '/modules/colissage/routes.php',
    BASE_PATH . '/modules/rh/routes.php',
    BASE_PATH . '/modules/logistique/routes.php',
    BASE_PATH . '/modules/administration/routes.php',
    BASE_PATH . '/modules/finance/routes.php',
    BASE_PATH . '/modules/supervision/routes.php',
];

foreach ($moduleRoutes as $routeFile) {
    if (file_exists($routeFile)) {
        require $routeFile;
    }
}
