<?php

declare(strict_types=1);

use App\View\Components\FinanceFonds;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var \App\Models\Finance\DemandeFonds $demande
 * @var array<int, array<string, mixed>> $historique
 * @var bool $canValidate
 * @var bool $canDecaisser
 * @var bool $canImputer
 */

echo FinanceFonds::fichePage(
    $demande,
    $historique ?? [],
    (bool) ($canValidate ?? false),
    (bool) ($canDecaisser ?? false),
    (bool) ($canImputer ?? false)
);
