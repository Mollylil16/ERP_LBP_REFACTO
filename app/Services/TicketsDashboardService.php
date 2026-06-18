<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TicketsDashboardRepository;

final class TicketsDashboardService extends AbstractModuleDashboardService
{
    public function __construct(TicketsDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
