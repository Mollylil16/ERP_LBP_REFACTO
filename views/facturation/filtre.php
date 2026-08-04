<?php

declare(strict_types=1);

use App\View\Components\Facturation;

/** @var int $startMonth */
/** @var int $startYear */
/** @var int $endMonth */
/** @var int $endYear */
/** @var int $selectedAgenceId */
/** @var string $selectedTrajet */
/** @var bool $canSeeAllAgencies */
/** @var array<int, array<string, mixed>> $sites */
/** @var array<int, array<string, mixed>> $trajets */
/** @var array<int, array<string, mixed>> $results */
/** @var array<string, mixed> $kpis */
/** @var array{currentPage: int, totalPages: int, itemsPerPage: int, totalItems: int}|null $pagination */

echo Facturation::filtrePage(
    $startMonth,
    $startYear,
    $endMonth,
    $endYear,
    $selectedAgenceId,
    $selectedTrajet,
    $canSeeAllAgencies,
    $sites,
    $trajets,
    $results,
    $kpis,
    $pagination ?? ['currentPage' => 1, 'totalPages' => 1, 'itemsPerPage' => 50, 'totalItems' => 0]
);
