<?php

declare(strict_types=1);

use App\View\Components\FinanceFondsHistorique;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<int, array<string, mixed>> $lignes
 * @var array<string, mixed> $filtres
 * @var array<int, array{id: int, name: string}> $agences
 * @var array<int, array{id: int, name: string}> $auteurs
 */

echo FinanceFondsHistorique::page($lignes, $filtres, $agences, $auteurs);
