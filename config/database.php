<?php


/**
 * Database configuration file.
 *
 * This file contains the database connection settings for the application.
 * It retrieves values from environment variables, providing default values if they are not set.
 *
 * @return array The database configuration settings.
 */
return [
    'host' => $_SERVER['DB_HOST'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
    'port' => (int) ($_SERVER['DB_PORT'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306),
    'dbname' => $_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'lbp_db',
    'username' => $_SERVER['DB_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'admin',
    'password' => ($_SERVER['DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? '@Succes2019'),
    'charset' => $_SERVER['DB_CHARSET'] ?? $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4',
];
