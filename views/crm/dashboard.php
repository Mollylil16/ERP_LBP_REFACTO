<?php

declare(strict_types=1);

use App\View\Components\Crm;
use App\View\Pages\Crm\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 */

echo Crm::dashboardPage($dashboardModule);
