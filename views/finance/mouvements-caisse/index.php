<?php

declare(strict_types=1);

use App\View\Components\MouvementsCaisse;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());

/*
 * Le service rend le contrat à plat et financeView() l'étale en variables :
 * il n'existe pas de variable « $mouvements » à passer telle quelle. Le sac
 * de variables de la vue est donc rassemblé ici, clé par clé — et le tout
 * reste accepté d'un bloc si un appelant préfère le passer ainsi.
 */
echo MouvementsCaisse::page([
    'date' => $viewData->string('date', date('Y-m-d')),
    'filtres' => $viewData->array('filtres'),
    'caisses' => $viewData->array('caisses'),
    'agences' => $viewData->array('agences'),
    'kpis' => $viewData->array('kpis'),
    'cumuls' => $viewData->array('cumuls'),
    'versements' => $viewData->array('versements'),
    'retraits' => $viewData->array('retraits'),
    'peutSaisir' => $viewData->bool('peutSaisir'),
]);
