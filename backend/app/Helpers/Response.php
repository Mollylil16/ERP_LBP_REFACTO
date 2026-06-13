<?php

namespace App\Helpers;

use App\Core\Response as CoreResponse;

class Response
{
    public static function json(mixed $data, int $statusCode = 200): void
    {
        CoreResponse::json($data, $statusCode);
    }
}
