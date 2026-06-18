<?php

declare(strict_types=1);

namespace App\Repositories\Tickets;

final class TicketsDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('tickets');
    }
}
