<?php

declare(strict_types=1);

use App\View\Components\Logistique;
use App\View\Pages\Logistique\ParametresPage;

/**
 * @var ParametresPage $page
 */

echo Logistique::parametresPage($page->settings, $page->sites, $page->successMsg ?? null);
