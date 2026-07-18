<?php

declare(strict_types=1);

// 'href' tag for unit testing constraints
use App\View\Components\Dashboard;
use App\View\Components\Logistique;
use App\View\Pages\Logistique\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 * @see Dashboard::kpis
 */

echo Logistique::dashboardPage($page, $dashboardModule);
