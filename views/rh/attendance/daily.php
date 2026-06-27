<?php
use App\View\Components\Attendance;
use App\View\Pages\Rh\AttendanceDailyPage;

/** @var AttendanceDailyPage $page */

ob_start();
echo Attendance::dailyPage($page);
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';

