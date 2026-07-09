<?php


/**
 * Database configuration file.
 *
 * This file contains the database connection settings for the application.
 * It retrieves values from environment variables, providing default values if they are not set.
 *
 * @return array The database configuration settings.
 */
$host = $_SERVER['DB_HOST'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST');
$port = $_SERVER['DB_PORT'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT');
$dbname = $_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
$username = $_SERVER['DB_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME');
$password = $_SERVER['DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
$charset = $_SERVER['DB_CHARSET'] ?? $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET');

return [
    'host' => ($host !== false && $host !== null) ? $host : 'localhost',
    'port' => (int) (($port !== false && $port !== null) ? $port : 3306),
    'dbname' => ($dbname !== false && $dbname !== null) ? $dbname : 'lbp_db',
    'username' => ($username !== false && $username !== null) ? $username : 'admin',
    'password' => ($password !== false && $password !== null) ? (string)$password : '@Succes2019',
    'charset' => ($charset !== false && $charset !== null) ? $charset : 'utf8mb4',
];
