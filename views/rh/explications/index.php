<?php
use App\Helpers\Csrf;
use App\View\Components\Explications;
use App\View\Pages\Rh\ExplicationIndexPage;

/** @var ExplicationIndexPage $page */
/** @var string $tab */
/** @var array $metrics */

echo Explications::explicationsPage($page, $tab, $metrics, Csrf::token());
