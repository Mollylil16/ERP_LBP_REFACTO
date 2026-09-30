<?php

use App\View\Components\ApproCaisse;

/** @var \App\Support\ViewBag $viewData */
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());

echo ApproCaisse::page([
    'filtres' => $filtres ?? [],
    'lignes' => $lignes ?? [],
    'totaux' => $totaux ?? [],
    'agences' => $agences ?? [],
    'peutSaisir' => $peutSaisir ?? false,
    'peutValider' => $peutValider ?? false,
]);
