<?php
use App\Helpers\Csrf;
use App\View\Components\ContractRules;
use App\View\Pages\Rh\ContractRulesPage;

/** @var ContractRulesPage $page */

ob_start();
echo ContractRules::rulesPage($page, Csrf::token());
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';

