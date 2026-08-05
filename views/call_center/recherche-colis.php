<?php

declare(strict_types=1);

use App\View\Components\CallCenter;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<string, mixed>|null $searchResult */
/** @var array<int, mixed> $rayonsOverview */
/** @var string $searchQuery */

ob_start();
echo CallCenter::colisLookupPage($searchResult, $rayonsOverview, $searchQuery);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
