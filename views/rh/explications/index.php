<?php
use App\Helpers\Csrf;
use App\View\Components\Explications;
use App\View\Pages\Rh\ExplicationIndexPage;

/** @var ExplicationIndexPage $page */
/** @var string $tab */
/** @var array $metrics */

ob_start();
echo Explications::explicationsPage($page, $tab, $metrics, Csrf::token());
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
