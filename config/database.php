<?php

return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'dbname' => getenv('DB_DATABASE') ?: 'lbp_db',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];
