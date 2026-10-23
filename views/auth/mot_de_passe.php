<?php

declare(strict_types=1);

use App\View\Components\Auth;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());

ob_start();
echo Auth::changementMotDePasse((string) $nomComplet, (bool) $obligatoire);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/guest.php';
