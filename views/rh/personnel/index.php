<?php

use App\View\Components\Personnel;
use App\View\Pages\Rh\PersonnelIndexPage;

/** @var PersonnelIndexPage $page */

ob_start();
echo Personnel::personnelPage($page);
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
