<?php

use App\View\Components\CallCenter;

/** @var \App\Support\ViewBag $viewData */
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());

ob_start();
echo CallCenter::appelsPage($appels, $clients);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
