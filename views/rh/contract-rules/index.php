<?php
use App\Helpers\Csrf;
use App\View\Components\ContractRules;
use App\View\Pages\Rh\ContractRulesPage;

/** @var ContractRulesPage $page */

echo ContractRules::rulesPage($page, Csrf::token());

