<?php

use App\View\Components\Finance;

/** @var \App\Support\ViewBag $viewData */ 
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());

ob_start();
echo Finance::factureShowPage($facture, $paiements, $callbacks, $colis, $client);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
