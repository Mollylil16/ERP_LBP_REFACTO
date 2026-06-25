<?php
use App\Helpers\Csrf;
use App\View\Components\Validations;
use App\View\Pages\Rh\ValidationIndexPage;

/** @var ValidationIndexPage $page */
/** @var string $tab */

ob_start();
echo Validations::validationsPage($page, $tab, Csrf::token());
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
