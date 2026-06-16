<?php

namespace App\Repositories;

use PDO;

class RhAttendanceRepository
{
    public function __construct(private PDO $pdo) {}

    public function getMonthAttendances(int $month, int $year): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, e.employee_number, e.full_name
            FROM rh_attendances a
            INNER JOIN rh_employees e ON e.id = a.employee_id
            WHERE MONTH(a.date) = :month AND YEAR(a.date) = :year
            ORDER BY a.date DESC, e.full_name ASC
        ");
        $stmt->execute(['month' => $month, 'year' => $year]);
        return $stmt->fetchAll() ?: [];
    }

    public function dailySheet(string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.id AS employee_id, e.employee_number, e.full_name,
                COALESCE(s.name, 'Service non renseigné') AS service_name,
                COALESCE(f.name, 'Fonction non renseignée') AS function_name,
                a.check_in, a.check_out, a.total_hours, a.overtime_hours,
                COALESCE(a.status, 'present') AS status
            FROM rh_employees e
            LEFT JOIN rh_services s ON s.id = e.service_id
            LEFT JOIN rh_functions f ON f.id = e.function_id
            LEFT JOIN rh_attendances a ON a.employee_id = e.id AND a.date = :date
            WHERE e.is_active = 1 AND e.exit_date IS NULL
            ORDER BY e.full_name ASC
        ");
        $stmt->execute(['date' => $date]);

        return $stmt->fetchAll() ?: [];
    }

    public function upsert(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_attendances (employee_id, date, check_in, check_out, total_hours, overtime_hours, status, updated_at)
            VALUES (:employee_id, :date, :check_in, :check_out, :total_hours, :overtime_hours, :status, NOW())
            ON DUPLICATE KEY UPDATE
                check_in = VALUES(check_in),
                check_out = VALUES(check_out),
                total_hours = VALUES(total_hours),
                overtime_hours = VALUES(overtime_hours),
                status = VALUES(status),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            'employee_id' => $data['employee_id'],
            'date' => $data['date'],
            'check_in' => $data['check_in'] ?: null,
            'check_out' => $data['check_out'] ?: null,
            'total_hours' => $data['total_hours'] ?? 0,
            'overtime_hours' => $data['overtime_hours'] ?? 0,
            'status' => $data['status'] ?? 'present',
        ]);
    }

    public function activeEmployeeIds(): array
    {
        $stmt = $this->pdo->query("SELECT id FROM rh_employees WHERE is_active = 1 AND exit_date IS NULL");
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
