<?php

declare(strict_types=1);

// 'href' tag for unit testing constraints
use App\View\Components\Dashboard;
use App\View\Components\Finance;
use App\View\Pages\Finance\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 * @see Dashboard::kpis
 */

echo Finance::dashboardPage($page, $dashboardModule);
