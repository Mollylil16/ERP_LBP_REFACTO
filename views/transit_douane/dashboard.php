<?php

declare(strict_types=1);

use App\View\Components\TransitDouane;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<string, mixed> $donnees */
/** @var string $agenceLabel */

ob_start();
echo TransitDouane::dossiersPage($donnees, $agenceLabel);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
