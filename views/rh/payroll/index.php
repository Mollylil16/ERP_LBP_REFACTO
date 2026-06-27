<?php
use App\Helpers\Csrf;
use App\View\Components\Payroll;
use App\View\Pages\Rh\PayrollIndexPage;

/** @var PayrollIndexPage $page */

ob_start();
echo Payroll::payrollPage($page, Csrf::token());
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
