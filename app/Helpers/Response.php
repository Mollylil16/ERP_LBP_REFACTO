<?php

namespace App\Helpers;

/**
 * Fournit des réponses HTTP simples au format JSON.
 */
class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }
}
