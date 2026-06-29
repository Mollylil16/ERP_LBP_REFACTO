<?php
use App\Helpers\Csrf;
use App\View\Components\Payroll;
use App\View\Pages\Rh\PayrollIndexPage;

/** @var PayrollIndexPage $page */

echo Payroll::payrollPage($page, Csrf::token());
