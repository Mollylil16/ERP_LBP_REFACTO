<?php

declare(strict_types=1);

use App\View\Components\PilotageDg;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<int, array<string, mixed>> $workflows */
/** @var array<int, array<string, mixed>> $legalRequests */
/** @var array<int, array<string, mixed>> $paymentRequests */

ob_start();
echo PilotageDg::validationsPage($workflows, $legalRequests, $paymentRequests);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
