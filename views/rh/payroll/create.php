<?php

use App\Helpers\Csrf;
use App\View\Components\Payroll;
use App\View\Pages\Rh\PayrollWizardPage;

/** @var PayrollWizardPage $page */

ob_start();
echo Payroll::wizardPage($page, Csrf::generate(), $contracts ?? []);
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
