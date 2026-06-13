<?php

namespace App\Repositories;

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
        ];
    }

    public function getAnalytics(): array
    {
        $analytics = [
            'attendanceRows' => 0,
            'presenceRate' => 0.0,
            'lateRows' => 0,
            'overtimeHours' => 0.0,
            'requestsProcessed' => 0,
        ];

        if ($this->tableExists('rh_attendance_daily')) {
            $row = $this->pdo->query("
                SELECT
                    COUNT(*) AS total_rows,
                    SUM(CASE WHEN attendance_status IN ('present', 'mission', 'conge') THEN 1 ELSE 0 END) AS present_rows,
                    SUM(CASE WHEN check_in_time > '08:00:00' AND attendance_status IN ('present', 'half_day') THEN 1 ELSE 0 END) AS late_rows,
                    SUM(overtime_hours) AS overtime_hours
                FROM rh_attendance_daily
                WHERE attendance_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
            ")->fetch() ?: [];

            $total = (int) ($row['total_rows'] ?? 0);
            $present = (int) ($row['present_rows'] ?? 0);
            $analytics['attendanceRows'] = $total;
            $analytics['presenceRate'] = $total > 0 ? round(($present / $total) * 100, 1) : 0.0;
            $analytics['lateRows'] = (int) ($row['late_rows'] ?? 0);
            $analytics['overtimeHours'] = round((float) ($row['overtime_hours'] ?? 0), 1);
        }

        if ($this->tableExists('employee_legal_requests')) {
            $analytics['requestsProcessed'] = $this->countWhere(
                'employee_legal_requests',
                "assigned_team = 'rh' AND status IN ('approved', 'rejected', 'cancelled')
                 AND DATE(COALESCE(decided_at, submitted_at, created_at))
                     BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())"
            );
        }

        return $analytics;
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
}
