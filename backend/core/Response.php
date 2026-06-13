<?php

namespace App\Core;

class Response
{
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(array $data = [], string $message = 'OK', int $statusCode = 200): void
    {
        self::json(array_merge(['status' => 'success', 'message' => $message], $data), $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, array $details = []): void
    {
        self::json(array_merge(['status' => 'error', 'message' => $message], $details), $statusCode);
    }
}
