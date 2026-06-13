<?php

namespace App\Core;

class Request
{
    public static function all(): array
    {
        return array_merge(self::jsonPayload(), self::post(), self::query());
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $data = self::all();
        return $data[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        $data = self::all();
        return array_key_exists($key, $data);
    }

    public static function query(): array
    {
        return filter_input_array(INPUT_GET, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
    }

    public static function post(): array
    {
        return filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
    }

    public static function jsonPayload(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    public static function header(string $name): ?string
    {
        $headers = self::headers();
        return $headers[$name] ?? $headers[strtolower($name)] ?? null;
    }

    public static function headers(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
                $headers[strtolower($headerName)] = $value;
            }
        }

        return $headers;
    }
}
