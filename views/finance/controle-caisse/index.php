<?php

use App\View\Components\ControleCaisse;

/** @var \App\Support\ViewBag $viewData */
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());

echo ControleCaisse::page([
    'filtres' => $filtres ?? [],
    'agences' => $agences ?? [],
    'caisses' => $caisses ?? [],
    'totaux' => $totaux ?? [],
    'sansPoint' => $sansPoint ?? [],
    'apresSoumission' => $apresSoumission ?? [],
    'ecarts' => $ecarts ?? [],
]);
