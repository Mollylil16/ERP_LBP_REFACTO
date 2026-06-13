<?php

namespace App\Core;

class Config
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $parts = explode('.', $key);
        $file = array_shift($parts);
        $configPath = BASE_PATH . '/config/' . $file . '.php';

        if (!file_exists($configPath)) {
            return $default;
        }

        $config = require $configPath;
        $value = self::getValue($config, $parts, $default);

        self::$cache[$key] = $value;

        return $value;
    }

    private static function getValue(array $config, array $keys, mixed $default): mixed
    {
        if (empty($keys)) {
            return $config;
        }

        $key = array_shift($keys);

        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];

        if (empty($keys)) {
            return $value;
        }

        return is_array($value) ? self::getValue($value, $keys, $default) : $default;
    }
}
