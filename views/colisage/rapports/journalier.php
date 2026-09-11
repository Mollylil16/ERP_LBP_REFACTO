<?php

declare(strict_types=1);

use App\View\Components\ColisageRapports;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var string $date
 * @var int|null $agenceId
 * @var array<int, array<string, mixed>> $sites
 * @var array<int, array<string, mixed>> $rapportColis
 * @var array<int, array<string, mixed>> $creditsMap
 * @var array<string, mixed> $totaux
 * @var bool $canExportExcel
 */

echo ColisageRapports::journalierPage(
    (string) $date,
    $agenceId !== null ? (int) $agenceId : null,
    $sites ?? [],
    $rapportColis ?? [],
    $creditsMap ?? [],
    $totaux ?? [],
    (bool) ($canExportExcel ?? false)
);
