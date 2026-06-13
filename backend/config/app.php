<?php

return [
    'name' => 'La Belle Porte',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:3001',
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'default_secret',
    'jwt_expires_in' => (int)($_ENV['JWT_EXPIRES_IN'] ?? 3600),
];
