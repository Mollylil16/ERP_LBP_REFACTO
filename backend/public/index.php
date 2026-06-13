<?php

declare(strict_types=1);

// Configuration CORS pour le frontend React
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once __DIR__ . '/../bootstrap/app.php';

use App\Router;

$router = new Router();

require BASE_PATH . '/routes/api.php';
require BASE_PATH . '/routes/web.php';

$router->dispatch(
    $_SERVER['REQUEST_URI'],
    $_SERVER['REQUEST_METHOD']
);
