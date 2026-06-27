<?php
use App\Helpers\Csrf;
use App\View\Components\Missions;
use App\View\Pages\Rh\MissionIndexPage;

/** @var MissionIndexPage $page */

$searchVal = trim((string)($_GET['search'] ?? ''));
$statusVal = trim((string)($_GET['status'] ?? ''));

ob_start();
echo Missions::missionsIndexPage($page, $searchVal, $statusVal, Csrf::token());
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
