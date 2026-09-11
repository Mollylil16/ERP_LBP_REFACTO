<?php

declare(strict_types=1);

use App\Helpers\Auth;
use App\View\Components\CallCenterEcrans;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<string, mixed> $kpis
 * @var array<int, array<string, mixed>> $recentAppels
 * @var array<int, array<string, mixed>> $recentLitiges
 */

echo CallCenterEcrans::tableauDeBordPage(
    $kpis ?? [],
    $recentAppels ?? [],
    $recentLitiges ?? [],
    Auth::can('call_center_manage')
);
