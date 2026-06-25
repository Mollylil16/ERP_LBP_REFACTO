<?php
use App\Helpers\Csrf;
use App\View\Components\Missions;

/** @var array<int,array<string,mixed>> $employees */
/** @var array<string,mixed>|null $mission */

$id = $mission ? (int)$mission['id'] : 0;
$status = $mission ? (string)$mission['status'] : 'draft';

$expenses = [];
if ($mission && !empty($mission['expenses_json'])) {
    $expenses = json_decode($mission['expenses_json'], true) ?: [];
}

ob_start();
echo Missions::missionFormPage($employees, $mission, Csrf::token(), $id, $status, $expenses);
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
