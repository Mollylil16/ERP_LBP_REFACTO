<?php

declare(strict_types=1);

namespace App\Repositories\Rh;

use PDO;

final class RhAttendanceRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function getActiveEmployees(): array
    {
        return $this->pdo->query("
            SELECT id, full_name, employee_number
            FROM rh_employees
            WHERE is_active = 1
            ORDER BY full_name ASC
        ")->fetchAll() ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function getDailyAttendance(string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.id AS employee_id, e.full_name, e.employee_number,
                   a.id AS attendance_id, a.check_in_time, a.check_out_time,
                   COALESCE(a.attendance_status, 'present') AS attendance_status,
                   a.worked_hours, a.overtime_hours, a.notes
            FROM rh_employees e
            LEFT JOIN rh_attendance_daily a ON a.employee_id = e.id AND a.attendance_date = :date
            WHERE e.is_active = 1
            ORDER BY e.full_name ASC
        ");
        $stmt->execute(['date' => $date]);
        return $stmt->fetchAll() ?: [];
    }

    public function saveDailyAttendance(string $date, array $records): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO rh_attendance_daily
                    (employee_id, attendance_date, check_in_time, check_out_time, attendance_status, worked_hours, overtime_hours, source, notes, created_at)
                VALUES
                    (:employee_id, :date, :in, :out, :status, :worked, :ot, 'manual', :notes, NOW())
                ON DUPLICATE KEY UPDATE
                    check_in_time = VALUES(check_in_time),
                    check_out_time = VALUES(check_out_time),
                    attendance_status = VALUES(attendance_status),
                    worked_hours = VALUES(worked_hours),
                    overtime_hours = VALUES(overtime_hours),
                    notes = VALUES(notes),
                    updated_at = NOW()
            ");

            foreach ($records as $employeeId => $record) {
                $in = !empty($record['check_in_time']) ? $record['check_in_time'] : null;
                $out = !empty($record['check_out_time']) ? $record['check_out_time'] : null;
                $status = !empty($record['attendance_status']) ? $record['attendance_status'] : 'present';
                $worked = (float) ($record['worked_hours'] ?? ($in && $out ? 8.0 : 0.0));
                $ot = (float) ($record['overtime_hours'] ?? 0.0);
                $notes = !empty($record['notes']) ? trim((string)$record['notes']) : null;

                $stmt->execute([
                    'employee_id' => (int) $employeeId,
                    'date' => $date,
                    'in' => $in,
                    'out' => $out,
                    'status' => $status,
                    'worked' => $worked,
                    'ot' => $ot,
                    'notes' => $notes
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function getMonthlyAttendance(int $employeeId, string $month): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM rh_attendance_daily
            WHERE employee_id = :employee_id AND attendance_date LIKE :month
            ORDER BY attendance_date ASC
        ");
        $stmt->execute([
            'employee_id' => $employeeId,
            'month' => $month . '%'
        ]);
        return $stmt->fetchAll() ?: [];
    }
}
