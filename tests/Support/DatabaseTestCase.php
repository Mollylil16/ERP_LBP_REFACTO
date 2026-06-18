<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use Tests\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected function sqlite(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
