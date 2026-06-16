<?php

use App\Helpers\View;

$moduleNavigation = [
    ['key' => 'dashboard',    'label' => 'Tableau de bord',     'icon' => 'DB', 'url' => 'logistique',                      'available' => true],
    ['key' => 'prestataires', 'label' => 'Prestataires',         'icon' => 'PS', 'url' => 'logistique/prestataires',         'available' => true],
    ['key' => 'factures',     'label' => 'Factures',             'icon' => 'FA', 'url' => 'logistique/factures',             'available' => true],
    ['key' => 'retraits',     'label' => 'Retraits Hub',         'icon' => 'RH', 'url' => 'logistique/retraits',             'available' => true],
    ['key' => 'fournitures',  'label' => 'Fournitures Agences',  'icon' => 'FN', 'url' => 'logistique/fournitures',          'available' => true],
    ['key' => 'credits',      'label' => 'Crédits Inter-Agences','icon' => 'CR', 'url' => 'logistique/credits',              'available' => true],
];

$moduleTheme = [
    'accent'    => '#7c3aed',
    'accent2'   => '#4c1d95',
    'gradient'  => 'linear-gradient(135deg, #4c1d95, #7c3aed)',
    'iconKey'   => 'logistique',
];
