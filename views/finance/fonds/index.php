<?php

declare(strict_types=1);

use App\View\Components\FinanceFonds;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<int, \App\Models\Finance\DemandeFonds> $items
 * @var int $total
 * @var int $page
 * @var int $totalPages
 * @var array<string, mixed> $filters
 * @var array<string, mixed> $stats
 * @var array<int, array{id: int, name: string, code: string}> $agences
 */

echo FinanceFonds::listePage($items, (int) $total, (int) $page, (int) $totalPages, $filters, $stats, $agences);
