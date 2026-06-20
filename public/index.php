<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Controllers\Error\ErrorController;
use App\Helpers\Response;
use App\Router;

try {
    $router = new Router();

    require BASE_PATH . '/routes/web.php';
    require BASE_PATH . '/routes/api.php';

    $router->dispatch(
        $_SERVER['REQUEST_URI'],
        $_SERVER['REQUEST_METHOD']
    );
} catch (\Throwable $exception) {
    error_log(sprintf(
        '[ERP LBP] %s dans %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    if (str_starts_with(parse_url($requestUri, PHP_URL_PATH) ?: '/', '/api/')
        || str_contains($accept, 'application/json')) {
        Response::json([
            'ok' => false,
            'message' => 'Une erreur interne est survenue. Veuillez réessayer plus tard.',
        ], 500);
    }

    $detail = getenv('APP_ENV') === 'testing'
        ? sprintf(
            '%s: %s dans %s:%d',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
        : 'L’incident a été enregistré. Vous pouvez revenir au portail sans perdre votre session.';

    (new ErrorController())->show(500, $detail);
}
