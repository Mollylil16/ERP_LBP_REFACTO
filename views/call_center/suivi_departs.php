<?php

declare(strict_types=1);

use App\View\Components\CallCenterEcrans;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<int, array<string, mixed>> $grouped
 * @var array<int, array<string, mixed>> $sites
 * @var string $search
 * @var int|null $agenceId
 * @var bool $canManage
 * @var bool $canExportExcel
 */

echo CallCenterEcrans::suiviDepartsPage(
    $grouped ?? [],
    $sites ?? [],
    (string) ($search ?? ''),
    $agenceId !== null ? (int) $agenceId : null,
    (bool) ($canManage ?? false),
    (bool) ($canExportExcel ?? false)
);
