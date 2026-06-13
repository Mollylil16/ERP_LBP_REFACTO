<?php

return [
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '5432',
    'dbname' => $_ENV['DB_DATABASE'] ?? 'lbp_db',
    'username' => $_ENV['DB_USERNAME'] ?? 'lbp_ci',
    'password' => $_ENV['DB_PASSWORD'] ?? 'labelleporte',
    'charset' => 'utf8',
];
