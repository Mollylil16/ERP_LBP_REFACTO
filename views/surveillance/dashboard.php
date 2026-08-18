<?php

declare(strict_types=1);

use App\View\Components\Surveillance;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array $stats */
/** @var array $alerts */
/** @var array $employees */
/** @var array $trend */
/** @var array $rules */
/** @var array $filters */
/** @var array $users */
/** @var int $nbRecPending */

ob_start();
echo Surveillance::dashboardPage($stats, $alerts, $employees, $trend, $rules, $filters, $users, $nbRecPending);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
