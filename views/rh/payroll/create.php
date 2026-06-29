<?php

use App\Helpers\Csrf;
use App\View\Components\Payroll;
use App\View\Pages\Rh\PayrollWizardPage;

/** @var PayrollWizardPage $page */

echo Payroll::wizardPage($page, Csrf::token());
