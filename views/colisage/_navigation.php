<?php

use App\Helpers\View;

$moduleNavigation = [
    ['key' => 'dashboard',    'label' => 'Tableau de bord',   'icon' => 'dashboard',      'url' => 'colisage',                   'available' => true],
    ['key' => 'colis',        'label' => 'Gestion des Colis', 'icon' => 'inventory_2',    'url' => 'colisage/colis',             'available' => true],
    ['key' => 'expeditions',  'label' => 'Expéditions',       'icon' => 'local_shipping', 'url' => 'colisage/expeditions',       'available' => true],
    ['key' => 'inventaire',   'label' => 'Inventaire',        'icon' => 'inventory',      'url' => 'colisage/inventaire',        'available' => true],
];

$moduleTheme = [
    'accent'    => '#0369a1',
    'accent2'   => '#0c4a6e',
    'gradient'  => 'linear-gradient(135deg, #0c4a6e, #0369a1)',
    'iconKey'   => 'colisage',
];
