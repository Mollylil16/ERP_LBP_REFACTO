<?php
use App\Helpers\Csrf;
use App\View\Components\Holidays;
use App\View\Pages\Rh\HolidayIndexPage;

/** @var HolidayIndexPage $page */

echo Holidays::holidaysPage($page, Csrf::token());
