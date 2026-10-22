<?php

declare(strict_types=1);

use App\View\Components\MessagerieDirection;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());

/*
 * Toutes les discussions, pour le DG et l'administrateur (le contrôleur
 * répond 404 aux autres).
 */
ob_start();
echo MessagerieDirection::ecran($vue, $filtres);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
