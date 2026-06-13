<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Router;

$router = new Router();

require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';

$router->dispatch(
    $_SERVER['REQUEST_URI'],
    $_SERVER['REQUEST_METHOD']
);
