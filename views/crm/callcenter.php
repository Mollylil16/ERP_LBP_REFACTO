<?php

declare(strict_types=1);

use App\View\Components\Crm;
use App\View\Pages\Crm\CallCenterPage;

/**
 * @var CallCenterPage $page
 */

echo Crm::callCenterPage($page->searchResult, $page->rayonsOverview, $page->searchQuery);
