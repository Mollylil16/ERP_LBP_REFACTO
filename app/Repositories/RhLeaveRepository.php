<?php

namespace App\Repositories;

use PDO;

class RhLeaveRepository
{
    public function __construct(private PDO $pdo) {}

    public function getLeaveTypes(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM rh_leave_types ORDER BY id ASC");
        return $stmt->fetchAll() ?: [];
    }

    public function getAllRequests(): array
    {
        $stmt = $this->pdo->query("
            SELECT r.*, e.employee_number, e.full_name, t.name as type_name, t.deduct_from_balance, t.is_paid
            FROM rh_leave_requests r
            INNER JOIN rh_employees e ON e.id = r.employee_id
            INNER JOIN rh_leave_types t ON t.id = r.leave_type_id
            ORDER BY r.created_at DESC
        ");
        return $stmt->fetchAll() ?: [];
    }

    public function getEmployeeRequests(int $employeeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, t.name as type_name, t.deduct_from_balance, t.is_paid
            FROM rh_leave_requests r
            INNER JOIN rh_leave_types t ON t.id = r.leave_type_id
            WHERE r.employee_id = :employee_id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute(['employee_id' => $employeeId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getRequestById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_leave_requests WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createRequest(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_leave_requests (employee_id, leave_type_id, start_date, end_date, duration_days, reason, status)
            VALUES (:employee_id, :leave_type_id, :start_date, :end_date, :duration_days, :reason, 'pending')
        ");
        $stmt->execute([
            'employee_id' => $data['employee_id'],
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration_days' => $data['duration_days'],
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function updateStatus(int $id, string $status, int $actorId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_leave_requests 
            SET status = :status, approved_by = :actor_id, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'actor_id' => $actorId,
            'id' => $id,
        ]);
    }

    public function calculateBalance(int $employeeId, string $hireDate): float
    {
        $year = (int)date('Y');
        
        // 1. Get Opening Balance for current year
        $stmt = $this->pdo->prepare("SELECT days_acquired FROM rh_leave_opening_balance WHERE employee_id = :employee_id AND year = :year");
        $stmt->execute(['employee_id' => $employeeId, 'year' => $year]);
        $row = $stmt->fetch();
        $openingBalance = $row ? (float)$row['days_acquired'] : 0.0;

        // 2. Calculate Acquired Days (2.2 days / month since start of year or hire date)
        $startOfYear = "$year-01-01";
        $startDate = (strtotime($hireDate) > strtotime($startOfYear)) ? $hireDate : $startOfYear;
        
        $d1 = new \DateTime($startDate);
        $d2 = new \DateTime(); // Today
        
        // Only if we are not past the end of the year, but today is always <= today.
        $monthsWorked = 0;
        if ($d2 > $d1) {
            $diff = $d1->diff($d2);
            $monthsWorked = $diff->y * 12 + $diff->m + ($diff->d >= 15 ? 1 : 0); // Count half month if > 15 days
        }
        $acquiredDays = $monthsWorked * 2.2;

        // 3. Get Deducted Days (Approved requests this year that deduct from balance)
        $stmtDeduct = $this->pdo->prepare("
            SELECT COALESCE(SUM(r.duration_days), 0) as used_days
            FROM rh_leave_requests r
            INNER JOIN rh_leave_types t ON t.id = r.leave_type_id
            WHERE r.employee_id = :employee_id 
              AND r.status = 'approved'
              AND t.deduct_from_balance = 1
              AND YEAR(r.start_date) = :year
        ");
        $stmtDeduct->execute(['employee_id' => $employeeId, 'year' => $year]);
        $usedDays = (float)$stmtDeduct->fetchColumn();

        return round(($openingBalance + $acquiredDays) - $usedDays, 2);
    }
}
