<?php
use App\Helpers\Csrf;
use App\View\Components\Signatories;
use App\View\Pages\Rh\SignatoryIndexPage;

/** @var SignatoryIndexPage $page */

echo Signatories::signatoriesPage($page, Csrf::token());
