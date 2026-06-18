<?php

declare(strict_types=1);

namespace App\Repositories\Employee;

final class EmployeeDashboardRepository
{
    public function __construct(private EmployeePortalRepository $portal) {}

    /** @return array<string, mixed>|null */
    public function employee(int $employeeId): ?array { return $this->portal->employee($employeeId); }
    /** @return array<int, array<string, mixed>> */
    public function requests(int $employeeId): array { return $this->portal->requests($employeeId); }
    /** @return array<int, array<string, mixed>> */
    public function attendance(int $employeeId, string $from, string $to): array { return $this->portal->attendance($employeeId, $from, $to); }
    /** @return array<int, array<string, mixed>> */
    public function explanations(int $employeeId): array { return $this->portal->explanations($employeeId); }
    /** @return array<string, mixed> */
    public function leaveBalance(int $employeeId, int $year): array { return $this->portal->leaveBalance($employeeId, $year); }
    /** @return array<int, array<string, mixed>> */
    public function documents(int $employeeId): array { return $this->portal->documents($employeeId); }
}
