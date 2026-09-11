<?php

declare(strict_types=1);

use App\View\Components\CallCenterEcrans;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<int, array<string, mixed>> $sites
 * @var array<int, array<string, mixed>> $rayons
 * @var array<int, array<int, array<string, mixed>>> $colisParRayon
 * @var int|null $agenceId
 * @var int $colisHorsDelai
 * @var string $dernierRefresh
 */

echo CallCenterEcrans::rayonsPage(
    $sites ?? [],
    $rayons ?? [],
    $colisParRayon ?? [],
    $agenceId !== null ? (int) $agenceId : null,
    (int) ($colisHorsDelai ?? 0),
    (string) ($dernierRefresh ?? '')
);
