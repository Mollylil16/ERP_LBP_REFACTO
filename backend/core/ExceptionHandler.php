<?php

namespace App\Core;

class ExceptionHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
    }

    public static function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
        return false;
    }

    public static function handleException(\Throwable $exception): void
    {
        http_response_code($exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 500);
        
        $errorResponse = [
            'status' => 'error',
            'message' => $exception->getMessage()
        ];

        // Ajouter des détails en dev
        if (Config::get('app.env') === 'development') {
            $errorResponse['trace'] = $exception->getTraceAsString();
            $errorResponse['file'] = $exception->getFile();
            $errorResponse['line'] = $exception->getLine();
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($errorResponse);
        exit;
    }

    public static function handleFatalError(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleException(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }
}
