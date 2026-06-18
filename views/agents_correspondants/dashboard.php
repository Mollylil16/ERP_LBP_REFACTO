<?php

declare(strict_types=1);

use App\View\Components\Dashboard;

/** @var array<string,mixed> $dashboardModule */
$dashboardModule = $dashboardModule ?? [];

ob_start();
echo Dashboard::businessModuleDashboard($dashboardModule);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
