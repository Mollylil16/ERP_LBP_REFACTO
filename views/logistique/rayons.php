<?php

declare(strict_types=1);

use App\View\Components\Logistique;
use App\View\Pages\Logistique\RayonsPage;

/**
 * @var RayonsPage $page
 */

echo Logistique::rayonsListPage($page->rayons, $page->sites, $page->successMsg ?? null, $page->errorMsg ?? null);
