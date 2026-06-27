<?php

namespace App\Repositories\Rh;

use PDO;

/**
 * Centralise toutes les lectures SQL du tableau de bord RH.
 */
class RhDashboardRepository
{
    private ?array $tables = null;

    public function __construct(private PDO $pdo) {}

    public function getStats(): array
    {
        $row = $this->pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 AND exit_date IS NULL THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN is_active = 0 OR exit_date IS NOT NULL THEN 1 ELSE 0 END) AS inactive_count,
                SUM(CASE WHEN hire_date >= DATE_FORMAT(CURDATE(), '%Y-01-01') THEN 1 ELSE 0 END) AS current_year_hires,
                COUNT(DISTINCT CASE WHEN is_active = 1 THEN service_id END) AS services_count
            FROM rh_employees
        ")->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active_count'] ?? 0),
            'inactive' => (int) ($row['inactive_count'] ?? 0),
            'currentYearHires' => (int) ($row['current_year_hires'] ?? 0),
            'services' => (int) ($row['services_count'] ?? 0),
        ];
    }

    public function getServiceDistribution(): array
    {
        return $this->distribution(
            'LEFT JOIN rh_services r ON r.id = e.service_id',
            "COALESCE(NULLIF(TRIM(r.name), ''), 'Service non renseigne')"
        );
    }

    public function getFunctionDistribution(): array
    {
        return $this->distribution(
            'LEFT JOIN rh_functions r ON r.id = e.function_id',
            "COALESCE(NULLIF(TRIM(r.name), ''), 'Fonction non renseignee')"
        );
    }

    public function getStatusDistribution(): array
    {
        return $this->distribution(
            'LEFT JOIN rh_statuses r ON r.id = e.status_id',
            "COALESCE(NULLIF(TRIM(r.name), ''), 'Statut non renseigne')"
        );
    }

    public function getRecentHires(int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $stmt = $this->pdo->prepare("
            SELECT
                e.id,
                e.employee_number,
                e.full_name,
                e.hire_date,
                e.is_active,
                COALESCE(s.name, 'Service non renseigne') AS service_name,
                COALESCE(f.name, 'Fonction non renseignee') AS function_name,
                COALESCE(st.name, 'Statut non renseigne') AS status_name
            FROM rh_employees e
            LEFT JOIN rh_services s ON s.id = e.service_id
            LEFT JOIN rh_functions f ON f.id = e.function_id
            LEFT JOIN rh_statuses st ON st.id = e.status_id
            ORDER BY e.hire_date IS NULL ASC, e.hire_date DESC, e.full_name ASC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function getOperationalCounts(): array
    {
        return [
            'legalRequests' => $this->countWhere(
                'employee_legal_requests',
                "assigned_team = 'rh' AND status = 'submitted'"
            ),
            'explanations' => $this->countWhere(
                'rh_explanation_requests',
                "status IN ('pending_response', 'complement_requested')"
            ),
            'contractsMissing' => $this->countEmployeesWithoutContract(),
            'leaveOpeningMissing' => $this->countEmployeesWithoutLeaveOpening(),
            'salaryClaims' => $this->countWhere(
                'rh_salary_claims',
                "status IN ('submitted', 'under_review')"
            ),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function getRecentPendingRequests(int $limit = 6): array
    {
        if (!$this->tableExists('employee_legal_requests')) {
            return [];
        }

        $limit = max(1, min(12, $limit));
        $stmt = $this->pdo->prepare("
            SELECT
                r.id,
                r.request_type,
                r.status,
                r.submitted_at,
                r.created_at,
                e.full_name AS employee_name
            FROM employee_legal_requests r
            INNER JOIN rh_employees e ON e.id = r.employee_id
            WHERE r.assigned_team = 'rh' AND r.status = 'submitted'
            ORDER BY r.submitted_at DESC, r.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function getAnalytics(): array
    {
        $analytics = [
            'attendanceRows' => 0,
            'presenceRate' => 0.0,
            'lateRows' => 0,
            'overtimeHours' => 0.0,
            'requestsProcessed' => 0,
            'requestsApproved' => 0,
            'requestsRejected' => 0,
            'overtimeEmployees' => 0,
        ];

        if ($this->tableExists('rh_attendance_daily')) {
            $row = $this->pdo->query("
                SELECT
                    COUNT(*) AS total_rows,
                    SUM(CASE WHEN attendance_status IN ('present', 'mission', 'conge') THEN 1 ELSE 0 END) AS present_rows,
                    SUM(CASE WHEN check_in_time > '08:00:00' AND attendance_status IN ('present', 'half_day') THEN 1 ELSE 0 END) AS late_rows,
                    SUM(overtime_hours) AS overtime_hours,
                    COUNT(DISTINCT CASE WHEN overtime_hours > 0 THEN employee_id END) AS overtime_employees
                FROM rh_attendance_daily
                WHERE attendance_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
            ")->fetch() ?: [];

            $total = (int) ($row['total_rows'] ?? 0);
            $present = (int) ($row['present_rows'] ?? 0);
            $analytics['attendanceRows'] = $total;
            $analytics['presenceRate'] = $total > 0 ? round(($present / $total) * 100, 1) : 0.0;
            $analytics['lateRows'] = (int) ($row['late_rows'] ?? 0);
            $analytics['overtimeHours'] = round((float) ($row['overtime_hours'] ?? 0), 1);
            $analytics['overtimeEmployees'] = (int) ($row['overtime_employees'] ?? 0);
        }

        if ($this->tableExists('employee_legal_requests')) {
            $analytics['requestsProcessed'] = $this->countWhere(
                'employee_legal_requests',
                "assigned_team = 'rh' AND status IN ('approved', 'rejected', 'cancelled')
                 AND DATE(COALESCE(decided_at, submitted_at, created_at))
                     BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())"
            );
            $analytics['requestsApproved'] = $this->countWhere(
                'employee_legal_requests',
                "assigned_team = 'rh' AND status = 'approved'
                 AND DATE(COALESCE(decided_at, submitted_at, created_at))
                     BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())"
            );
            $analytics['requestsRejected'] = $this->countWhere(
                'employee_legal_requests',
                "assigned_team = 'rh' AND status = 'rejected'
                 AND DATE(COALESCE(decided_at, submitted_at, created_at))
                     BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())"
            );
        }

        return $analytics;
    }

    /** @return array<string,array{presence:int,absence:int,retard:int}> */
    public function getDailyAttendance(): array
    {
        if (!$this->tableExists('rh_attendance_daily')) {
            return [];
        }

        $stmt = $this->pdo->query("
            SELECT
                attendance_date,
                SUM(CASE WHEN attendance_status IN ('present', 'mission', 'conge') THEN 1 ELSE 0 END) AS presence,
                SUM(CASE WHEN attendance_status IN ('absent', 'maladie', 'suspension') THEN 1 ELSE 0 END) AS absence,
                SUM(CASE WHEN check_in_time > '08:00:00' AND attendance_status IN ('present', 'half_day') THEN 1 ELSE 0 END) AS retard
            FROM rh_attendance_daily
            WHERE attendance_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
            GROUP BY attendance_date
        ");

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $results[(string) $row['attendance_date']] = [
                'presence' => (int) $row['presence'],
                'absence' => (int) $row['absence'],
                'retard' => (int) $row['retard'],
            ];
        }

        return $results;
    }

    /** @return array<int,array{month:string,presenceRate:float,overtimeHours:float}> */
    public function getMonthlyTrend(): array
    {
        $trends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-$i months"));
            $monthEnd = date('Y-m-t', strtotime("-$i months"));
            $monthLabel = date('m/Y', strtotime("-$i months"));

            $presenceRate = 0.0;
            $overtimeHours = 0.0;

            if ($this->tableExists('rh_attendance_daily')) {
                $row = $this->pdo->query("
                    SELECT
                        COUNT(*) AS total_rows,
                        SUM(CASE WHEN attendance_status IN ('present', 'mission', 'conge') THEN 1 ELSE 0 END) AS present_rows,
                        SUM(overtime_hours) AS overtime_hours
                    FROM rh_attendance_daily
                    WHERE attendance_date BETWEEN '{$monthStart}' AND '{$monthEnd}'
                ")->fetch() ?: [];

                $total = (int) ($row['total_rows'] ?? 0);
                $present = (int) ($row['present_rows'] ?? 0);
                $presenceRate = $total > 0 ? round(($present / $total) * 100, 1) : 0.0;
                $overtimeHours = round((float) ($row['overtime_hours'] ?? 0), 1);
            }

            $trends[] = [
                'month' => $monthLabel,
                'presenceRate' => $presenceRate,
                'overtimeHours' => $overtimeHours,
            ];
        }

        return $trends;
    }

    private function distribution(string $join, string $labelExpression): array
    {
        $stmt = $this->pdo->query("
            SELECT {$labelExpression} AS label, COUNT(*) AS total
            FROM rh_employees e
            {$join}
            GROUP BY label
            ORDER BY total DESC, label ASC
            LIMIT 8
        ");

        return $stmt->fetchAll() ?: [];
    }

    private function countEmployeesWithoutContract(): int
    {
        if (!$this->tableExists('employee_contracts')) {
            return 0;
        }

        return (int) $this->pdo->query("
            SELECT COUNT(*)
            FROM rh_employees e
            LEFT JOIN employee_contracts c
                ON c.employee_id = e.id AND c.active = 1 AND c.status = 'active'
            WHERE e.is_active = 1 AND e.exit_date IS NULL AND c.id IS NULL
        ")->fetchColumn();
    }

    private function countEmployeesWithoutLeaveOpening(): int
    {
        if (!$this->tableExists('rh_leave_opening_balance')) {
            return 0;
        }

        return (int) $this->pdo->query("
            SELECT COUNT(*)
            FROM rh_employees e
            LEFT JOIN rh_leave_opening_balance b ON b.employee_id = e.id
            WHERE e.is_active = 1 AND e.exit_date IS NULL AND b.id IS NULL
        ")->fetchColumn();
    }

    private function countWhere(string $table, string $where): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        if ($this->tables === null) {
            $this->tables = [];
            foreach ($this->pdo->query('SHOW TABLES') as $row) {
                $this->tables[(string) array_values($row)[0]] = true;
            }
        }

        return isset($this->tables[$table]);
    }

    /** @return array<int,array{id:int,full_name:string,employee_number:string|null}> */
    public function getEmployeeList(): array
    {
        if (!$this->tableExists('rh_employees')) {
            return [];
        }

        return $this->pdo->query("
            SELECT id, full_name, employee_number
            FROM rh_employees
            WHERE is_active = 1
            ORDER BY full_name ASC
        ")->fetchAll() ?: [];
    }
}
