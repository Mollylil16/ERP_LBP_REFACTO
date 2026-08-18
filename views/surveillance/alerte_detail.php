<?php

declare(strict_types=1);

use App\View\Components\Surveillance;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array $alert */

ob_start();
echo Surveillance::alerteDetailPage($alert);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
