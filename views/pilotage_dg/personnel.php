<?php

declare(strict_types=1);

use App\View\Components\PilotageDg;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<int, array<string, mixed>> $employees */
/** @var array<int, array<string, mixed>> $topHonnetes */
/** @var array<int, array<string, string>> $alerts */

$topHonnetes ??= [];
ob_start();
echo PilotageDg::personnelPage($employees, $alerts, $topHonnetes);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
