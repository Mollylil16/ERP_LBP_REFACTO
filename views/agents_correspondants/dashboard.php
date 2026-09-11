<?php

declare(strict_types=1);

use App\View\Components\AgentsCorrespondants;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<string, mixed> $donnees */
/** @var bool $peutGerer */
/** @var array<string, mixed>|null $enEdition */

ob_start();
echo AgentsCorrespondants::reseauPage($donnees, (bool) $peutGerer, $enEdition ?? null);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
