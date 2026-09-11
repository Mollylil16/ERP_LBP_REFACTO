<?php

declare(strict_types=1);

use App\View\Components\CallCenterEcrans;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<int, array<string, mixed>> $colisList
 * @var string $search
 * @var bool $canManage
 */

echo CallCenterEcrans::suiviPage(
    $colisList ?? [],
    (string) ($search ?? ''),
    (bool) ($canManage ?? false)
);
