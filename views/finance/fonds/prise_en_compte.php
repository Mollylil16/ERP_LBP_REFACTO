<?php

declare(strict_types=1);

use App\View\Components\FinanceFondsFile;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<int, \App\Models\Finance\DemandeFonds> $items
 * @var int $total
 * @var int $page
 * @var array<string, mixed> $filters
 * @var array<int, array{id: int, name: string, code: string}> $agences
 */

echo FinanceFondsFile::priseEnComptePage($items, (int) $total, (int) $page, $filters, $agences);
