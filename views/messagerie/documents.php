<?php

declare(strict_types=1);

use App\View\Components\MessagerieDocuments;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());

/*
 * Le classeur des documents. La charpente vient du composant ; les documents
 * sont placés par public/assets/js/messagerie-documents.js, qui lit l'API.
 */
ob_start();
echo MessagerieDocuments::ecran();
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
