<?php

namespace App\Core;

use App\Models\Database as ModelDatabase;
use PDO;

class Database
{
    public static function getConnection(): PDO
    {
        return ModelDatabase::getConnection();
    }
}
