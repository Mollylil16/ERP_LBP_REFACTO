<?php

declare(strict_types=1);

namespace App\Services\Employee;

use App\Models\User;
use App\Repositories\Employee\EmployeeDashboardRepository;
use RuntimeException;

final class EmployeeDashboardService
{
    public function __construct(private EmployeeDashboardRepository $repository) {}

    /** @return array<string, mixed> */
    public function dashboard(User $user): array
    {
        $employeeId = (int) ($user->rhEmployeeId ?? 0);
        if ($employeeId <= 0) {
            throw new RuntimeException('Ce compte utilisateur n’est lié à aucun dossier collaborateur.');
        }

        $employee = $this->repository->employee($employeeId);
        if (!$employee) {
            throw new RuntimeException('Le dossier collaborateur lié à ce compte est introuvable.');
        }

        $requests = $this->repository->requests($employeeId);
        $attendance = $this->repository->attendance($employeeId, date('Y-m-01'), date('Y-m-t'));
        $explanations = $this->repository->explanations($employeeId);
        $balance = $this->repository->leaveBalance($employeeId, (int) date('Y'));
        $present = count(array_filter($attendance, static fn(array $row): bool => in_array($row['attendance_status'], ['present', 'mission', 'conge'], true)));

        return compact('employee', 'requests', 'attendance', 'explanations', 'balance') + [
            'documents' => $this->repository->documents($employeeId),
            'stats' => [
                'openRequests' => count(array_filter($requests, static fn(array $row): bool => !in_array($row['status'], ['approved', 'rejected', 'cancelled'], true))),
                'pendingExplanations' => count(array_filter($explanations, static fn(array $row): bool => in_array($row['status'], ['pending_response', 'complement_requested'], true))),
                'presenceRate' => $attendance === [] ? 0 : round(($present / count($attendance)) * 100, 1),
                'leaveRemaining' => round((float) $balance['opening_days'] + (float) $balance['acquired_days'] - (float) $balance['taken_days'], 2),
            ],
        ];
    }
}
