<?php

use App\Helpers\View;

$moduleNavigation = [
    ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => 'crm',          'available' => true],
    ['key' => 'clients',   'label' => 'Clients',         'icon' => 'CL', 'url' => 'crm/clients',  'available' => true],
];

$moduleTheme = [
    'accent'    => '#0f766e',
    'accent2'   => '#115e59',
    'gradient'  => 'linear-gradient(135deg, #115e59, #0f766e)',
    'iconKey'   => 'crm',
];
