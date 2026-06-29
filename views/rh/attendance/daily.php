<?php
use App\View\Components\Attendance;
use App\View\Pages\Rh\AttendanceDailyPage;

/** @var AttendanceDailyPage $page */

echo Attendance::dailyPage($page);

