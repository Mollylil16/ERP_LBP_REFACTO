<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Repositories\Tickets\TicketsDashboardRepository;

final class TicketsDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(TicketsDashboardRepository $repository)
    {
        parent::__construct($repository);
    }
}
