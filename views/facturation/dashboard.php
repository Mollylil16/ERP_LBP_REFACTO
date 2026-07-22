<?php

declare(strict_types=1);

// 'href' tag for unit testing constraints
use App\View\Components\Dashboard;
use App\View\Components\Facturation;
use App\View\Pages\Facturation\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 * @see Dashboard::kpis
 */

echo Facturation::dashboardPage($page, $dashboardModule);
