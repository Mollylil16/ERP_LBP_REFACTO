<?php

return [
    'name' => 'ERP LBP Transit',
    'tagline' => 'Plateforme de gestion des opérations de transit et de logistique.',
    'url' => rtrim((string) (getenv('APP_URL') ?: 'http://localhost/ERP_LBP_REFACTO'), '/'),

    'theme' => [
        'primary' => '#1d2b57',
        'secondary' => '#fabd02',
    ],
];
