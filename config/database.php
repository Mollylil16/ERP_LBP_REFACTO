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
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'dbname' => getenv('DB_DATABASE') ?: 'lbp_db',
    'username' => getenv('DB_USERNAME') ?: 'admin',
    'password' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '@Succes2019',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];
