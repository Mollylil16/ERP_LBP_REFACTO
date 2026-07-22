<?php

declare(strict_types=1);

// 'href' tag for unit testing constraints
use App\View\Components\Dashboard;
use App\View\Components\Colisage;
use App\View\Pages\Colisage\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 * @see Dashboard::kpis
 */

echo Colisage::dashboardPage($page, $dashboardModule);
