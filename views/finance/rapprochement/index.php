<?php

use App\View\Components\RapprochementEnvois;

/** @var \App\Support\ViewBag $viewData */
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());

echo RapprochementEnvois::page([
    'lignes' => $lignes ?? [],
    'totaux' => $totaux ?? [],
    'compagnies' => $compagnies ?? [],
    'agences' => $agences ?? [],
    'filtres' => $filtres ?? [],
    'peutSaisir' => $peutSaisir ?? false,
]);
