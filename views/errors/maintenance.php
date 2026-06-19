<?php

use App\View\Components\ErrorState;
use App\View\Pages\Error\ErrorPage;

/** @var ErrorPage $page */

ob_start();
?>
<?= ErrorState::page($page) ?>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/guest.php';
