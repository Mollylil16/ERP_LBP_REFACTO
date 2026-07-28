<?php

declare(strict_types=1);

use App\View\Components\Dashboard;
use App\View\Components\Crm;
use App\View\Pages\Crm\DashboardPage;

/**
 * @var array<string,mixed> $dashboardModule
 * @var DashboardPage $page
 */

// Dashboard module CRM with clickable KPI links ('href') Dashboard::kpis
echo Crm::dashboardPage($dashboardModule);
