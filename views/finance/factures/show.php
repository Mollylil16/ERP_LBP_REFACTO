<?php

use App\View\Components\Finance;

/** @var \App\Support\ViewBag $viewData */ 
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());

echo Finance::factureShowPage($facture, $paiements, $callbacks, $colis, $client);
