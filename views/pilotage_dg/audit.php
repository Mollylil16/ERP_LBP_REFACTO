<?php

declare(strict_types=1);

use App\View\Components\PilotageDg;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<int, array<string, mixed>> $logs */
/** @var array<int, string> $entityTypes */
/** @var array{currentPage: int, totalPages: int, itemsPerPage: int, totalItems: int} $pagination */
/** @var array<string, string> $filters */

ob_start();
echo PilotageDg::auditPage($logs, $entityTypes, $pagination, $filters);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
