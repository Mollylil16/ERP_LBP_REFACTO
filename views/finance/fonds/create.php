<?php

declare(strict_types=1);

use App\View\Components\FinanceFondsFile;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/**
 * @var array<int, array{id: int, name: string, code: string}> $agences
 * @var array<int, string> $dossiersRecents
 * @var string $defaultNum
 * @var int $userAgenceId
 */

echo FinanceFondsFile::creationPage(
    $agences,
    $dossiersRecents ?? [],
    (string) $defaultNum,
    (int) $userAgenceId
);
