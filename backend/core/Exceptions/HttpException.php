<?php

namespace App\Core\Exceptions;

use Exception;

class HttpException extends Exception
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 400, ?Exception $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
