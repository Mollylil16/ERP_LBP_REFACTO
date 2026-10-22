<?php

declare(strict_types=1);

use App\View\Components\Messagerie;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());

/*
 * Écran complet de la messagerie. La charpente vient du composant ; les
 * discussions et les messages sont placés par public/assets/js/messagerie.js,
 * chargé par le gabarit sur toutes les pages des modules.
 */
ob_start();
echo Messagerie::ecran();
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
