<?php

use App\Helpers\Csrf;
use App\View\Components\PayrollEngine;
use App\View\Pages\Rh\PayrollEnginePage;

/** @var PayrollEnginePage $page */

echo PayrollEngine::enginePage($page, Csrf::token());
