<?php

use App\Helpers\Csrf;
use App\View\Components\PayrollEngine;
use App\View\Pages\Rh\PayrollEnginePage;

/** @var PayrollEnginePage $page */

ob_start();
echo PayrollEngine::enginePage($page, Csrf::token());
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
