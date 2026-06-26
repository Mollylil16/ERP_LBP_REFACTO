<?php
use App\Helpers\Csrf;
use App\View\Components\Holidays;
use App\View\Pages\Rh\HolidayIndexPage;

/** @var HolidayIndexPage $page */

ob_start();
echo Holidays::holidaysPage($page, Csrf::token());
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
