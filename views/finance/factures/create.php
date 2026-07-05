<?php

use App\View\Components\Finance;

/** @var \App\Support\ViewBag $viewData */ 
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());

ob_start();
echo Finance::factureCreateForm($colisSansFacture);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
