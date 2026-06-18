<?php

use App\View\Components\Dashboard;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<string, mixed> $dashboardModule */
$module = $dashboardModule;

ob_start();
echo Dashboard::businessModuleDashboard($module);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
