<?php

namespace App\Models;

/**
 * Donnees normalisees exposees par le dashboard RH.
 */
class RhDashboard
{
    public function __construct(
        public readonly array $stats,
        public readonly array $services,
        public readonly array $functions,
        public readonly array $statuses,
        public readonly array $recentHires,
        public readonly array $alerts,
        public readonly array $analytics,
        public readonly int $pendingTotal,
        public readonly array $pendingRequests = [],
        public readonly array $dailyAttendance = [],
        public readonly array $monthlyTrend = [],
        public readonly array $employeeList = [],
    ) {}
}
