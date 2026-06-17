<?php

use App\Helpers\View;

$moduleNavigation = [
    ['key' => 'dashboard',    'label' => 'Tableau de bord',     'icon' => 'dashboard',       'url' => 'logistique',                      'available' => true],
    ['key' => 'prestataires', 'label' => 'Prestataires',         'icon' => 'business',        'url' => 'logistique/prestataires',         'available' => true],
    ['key' => 'factures',     'label' => 'Factures',             'icon' => 'receipt_long',    'url' => 'logistique/factures',             'available' => true],
    ['key' => 'retraits',     'label' => 'Retraits Hub',         'icon' => 'account_balance', 'url' => 'logistique/retraits',             'available' => true],
    ['key' => 'fournitures',  'label' => 'Fournitures Agences',  'icon' => 'shopping_cart',   'url' => 'logistique/fournitures',          'available' => true],
    ['key' => 'credits',      'label' => 'Crédits Inter-Agences','icon' => 'swap_horiz',      'url' => 'logistique/credits',              'available' => true],
];

$moduleTheme = [
    'accent'    => '#7c3aed',
    'accent2'   => '#4c1d95',
    'gradient'  => 'linear-gradient(135deg, #4c1d95, #7c3aed)',
    'iconKey'   => 'logistique',
];
