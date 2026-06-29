<?php
use App\Helpers\Csrf;
use App\View\Components\Missions;
use App\View\Pages\Rh\MissionIndexPage;

/** @var MissionIndexPage $page */

$searchVal = trim((string)($_GET['search'] ?? ''));
$statusVal = trim((string)($_GET['status'] ?? ''));

echo Missions::missionsIndexPage($page, $searchVal, $statusVal, Csrf::token());
