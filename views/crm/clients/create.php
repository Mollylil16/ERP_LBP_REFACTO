<?php

declare(strict_types=1);

use App\View\Components\Crm;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<int, array<string, mixed>> $commercialOwners */

ob_start();
echo Crm::clientCreatePage($commercialOwners);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
