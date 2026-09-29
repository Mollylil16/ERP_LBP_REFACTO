<?php

declare(strict_types=1);

/**
 * Configuration Infobip SMS & Call Center LBP
 */
return [
    // Base URL Infobip
    'base_url' => $_SERVER['INFOBIP_BASE_URL'] ?? $_ENV['INFOBIP_BASE_URL'] ?? getenv('INFOBIP_BASE_URL') ?: 'https://6zzmpe.api.infobip.com',

    // Clé API Infobip
    'api_key' => $_SERVER['INFOBIP_API_KEY'] ?? $_ENV['INFOBIP_API_KEY'] ?? getenv('INFOBIP_API_KEY') ?: 'f70239956cbe9644bc1dd36c014289ec-83a000f1-81b3-44bf-b356-fbf4cb7a087b',

    // Nom d'expéditeur (Sender ID). "LBP" ou "InfoSMS" selon la politique opérateur
    'sender_id' => $_SERVER['INFOBIP_SENDER_ID'] ?? $_ENV['INFOBIP_SENDER_ID'] ?? getenv('INFOBIP_SENDER_ID') ?: 'LBP',

    // Numéros officiels du Call Center à inclure dans les messages
    'call_center_phones' => [
        '05-03-48-6161',
        '05-84-43-03-48',
        '05-03-46-79-79',
    ],
];
