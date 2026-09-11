<?php

declare(strict_types=1);

use App\Helpers\Auth;
use App\View\Components\CallCenterEcrans;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<int, array<string, mixed>> $litiges
 * @var array<int, array<string, mixed>> $clients
 * @var array<int, array<string, mixed>> $colis
 * @var string $statutFilter
 * @var string $graviteFilter
 * @var array<string, mixed>|null $aTraiter
 */

echo CallCenterEcrans::litigesPage(
    $litiges ?? [],
    $clients ?? [],
    $colis ?? [],
    (string) ($statutFilter ?? ''),
    (string) ($graviteFilter ?? ''),
    Auth::can('call_center_manage'),
    Auth::isAdmin() || Auth::hasRole('dg'),
    $aTraiter ?? null
);
