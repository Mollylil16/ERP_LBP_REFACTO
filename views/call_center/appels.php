<?php

declare(strict_types=1);

use App\Helpers\Auth;
use App\View\Components\CallCenterEcrans;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<int, array<string, mixed>> $appels
 * @var array<int, array<string, mixed>> $clients
 * @var string $dateDebut
 * @var string $dateFin
 * @var string $typeFilter
 */

echo CallCenterEcrans::journalAppelsPage(
    $appels ?? [],
    $clients ?? [],
    (string) ($dateDebut ?? ''),
    (string) ($dateFin ?? ''),
    (string) ($typeFilter ?? ''),
    Auth::can('call_center_manage'),
    Auth::isAdmin() || Auth::hasRole('dg')
);
