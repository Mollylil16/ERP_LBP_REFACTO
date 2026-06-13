<?php

namespace App\Helpers;

use App\Core\JWT as CoreJWT;

class JWT
{
    public static function encode(array $payload, string $secret, int $expirySeconds = 3600): string
    {
        return CoreJWT::encode($payload, $secret, $expirySeconds);
    }

    public static function decode(string $token, string $secret): ?array
    {
        return CoreJWT::decode($token, $secret);
    }
}
