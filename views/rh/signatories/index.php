<?php
use App\Helpers\Csrf;
use App\View\Components\Signatories;
use App\View\Pages\Rh\SignatoryIndexPage;

/** @var SignatoryIndexPage $page */

ob_start();
echo Signatories::signatoriesPage($page, Csrf::token());
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
