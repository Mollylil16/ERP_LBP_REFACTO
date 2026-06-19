<?php

use App\View\Components\Dashboard;
use App\View\Pages\SiteAdmin\DashboardPage;

/** @var DashboardPage $page */

ob_start();
echo Dashboard::businessModuleDashboard($page->module);
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
