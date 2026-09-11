<?php

declare(strict_types=1);

use App\View\Components\ColisageRapports;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var string $mois
 * @var int|null $agenceId
 * @var array<int, array<string, mixed>> $sites
 * @var array<int, array<string, mixed>> $journaliers
 */

echo ColisageRapports::mensuelPage(
    (string) $mois,
    $agenceId !== null ? (int) $agenceId : null,
    $sites ?? [],
    $journaliers ?? []
);
