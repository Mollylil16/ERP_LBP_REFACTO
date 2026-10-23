<?php

namespace App\Models;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = require BASE_PATH . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );

        try {
            self::$connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            /*
             * L'ERP vit à l'heure d'Abidjan, UTC+0 toute l'année (bootstrap :
             * date_default_timezone_set('Africa/Abidjan')). Le serveur MariaDB
             * de l'hébergeur, lui, suit l'heure d'été britannique : constaté le
             * 23/10/2026, NOW() avait une heure d'avance sur date() — et tout
             * ce que la base date elle-même (NOW(), CURRENT_TIMESTAMP) était
             * décalé de ce que PHP datait. La connexion est donc mise à UTC.
             */
            try {
                self::$connection->exec("SET time_zone = '+00:00'");
            } catch (PDOException $e) {
                error_log('[Database] fuseau de la connexion : ' . $e->getMessage());
            }

            return self::$connection;
        } catch (PDOException $e) {
            die('Erreur de connexion à la base de données.');
        }
    }
}
