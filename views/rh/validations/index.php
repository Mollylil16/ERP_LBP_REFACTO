<?php
use App\Helpers\Csrf;
use App\View\Components\Validations;
use App\View\Pages\Rh\ValidationIndexPage;

/** @var ValidationIndexPage $page */
/** @var string $tab */

echo Validations::validationsPage($page, $tab, Csrf::token());
