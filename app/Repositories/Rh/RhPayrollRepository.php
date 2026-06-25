<?php

declare(strict_types=1);

namespace App\Repositories\Rh;

use PDO;

final class RhPayrollRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function getPeriods(): array
    {
        return $this->pdo->query("
            SELECT *
            FROM rh_payroll_periods
            ORDER BY code DESC
        ")->fetchAll() ?: [];
    }

    public function createPeriod(string $code, string $start, string $end): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_payroll_periods (code, start_date, end_date, status, created_at)
            VALUES (:code, :start, :end, 'open', NOW())
        ");
        $stmt->execute(['code' => $code, 'start' => $start, 'end' => $end]);
    }

    /** @return array<int,array<string,mixed>> */
    public function getVariables(int $periodId): array
    {
        // Auto-seed variables for active employees who do not have records yet for this period
        $this->seedVariablesForPeriod($periodId);

        $stmt = $this->pdo->prepare("
            SELECT v.*, e.full_name, e.employee_number
            FROM rh_payroll_variables v
            INNER JOIN rh_employees e ON e.id = v.employee_id
            WHERE v.period_id = :period_id
            ORDER BY e.full_name ASC
        ");
        $stmt->execute(['period_id' => $periodId]);
        return $stmt->fetchAll() ?: [];
    }

    private function seedVariablesForPeriod(int $periodId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO rh_payroll_variables (period_id, employee_id, worked_days, absences_days, overtime_hours, bonus, deductions, created_at)
            SELECT :period_id, id, 30, 0, 0, 0, 0, NOW()
            FROM rh_employees
            WHERE is_active = 1
        ");
        $stmt->execute(['period_id' => $periodId]);
    }

    public function saveVariables(int $periodId, array $variables): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE rh_payroll_variables
                SET worked_days = :worked, absences_days = :absences, overtime_hours = :ot,
                    bonus = :bonus, deductions = :deductions, notes = :notes, updated_at = NOW()
                WHERE period_id = :period_id AND employee_id = :employee_id
            ");

            foreach ($variables as $employeeId => $var) {
                $stmt->execute([
                    'worked' => (float) ($var['worked_days'] ?? 30.0),
                    'absences' => (float) ($var['absences_days'] ?? 0.0),
                    'ot' => (float) ($var['overtime_hours'] ?? 0.0),
                    'bonus' => (float) ($var['bonus'] ?? 0.0),
                    'deductions' => (float) ($var['deductions'] ?? 0.0),
                    'notes' => !empty($var['notes']) ? trim((string)$var['notes']) : null,
                    'period_id' => $periodId,
                    'employee_id' => (int) $employeeId,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function calculateSlips(int $periodId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM rh_payroll_slips WHERE period_id = :period_id")->execute(['period_id' => $periodId]);

            $stmt = $this->pdo->prepare("
                SELECT v.*
                FROM rh_payroll_variables v
                WHERE v.period_id = :period_id
            ");
            $stmt->execute(['period_id' => $periodId]);
            $variables = $stmt->fetchAll() ?: [];

            $insert = $this->pdo->prepare("
                INSERT INTO rh_payroll_slips (period_id, employee_id, base_salary, bonuses_total, deductions_total, net_salary, status, created_at)
                VALUES (:period_id, :employee_id, :base, :bonus_total, :deduction_total, :net, 'draft', NOW())
            ");

            foreach ($variables as $var) {
                $base = 350000.0; // Default base salary
                $worked = (float) $var['worked_days'];
                $ot = (float) $var['overtime_hours'];
                $bonus = (float) $var['bonus'];
                $deductions = (float) $var['deductions'];

                // Simple payroll calculation formula
                $calculatedBase = ($worked / 30.0) * $base;
                $otAmount = $ot * 2500.0; // 2500 XOF per overtime hour
                $bonusTotal = $bonus + $otAmount;
                $net = $calculatedBase + $bonusTotal - $deductions;

                $insert->execute([
                    'period_id' => $periodId,
                    'employee_id' => (int) $var['employee_id'],
                    'base' => $calculatedBase,
                    'bonus_total' => $bonusTotal,
                    'deduction_total' => $deductions,
                    'net' => max(0.0, $net)
                ]);
            }

            // Update period status to calculating
            $this->pdo->prepare("UPDATE rh_payroll_periods SET status = 'calculating' WHERE id = :id")->execute(['id' => $periodId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function getSlips(int $periodId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, e.full_name, e.employee_number
            FROM rh_payroll_slips s
            INNER JOIN rh_employees e ON e.id = s.employee_id
            WHERE s.period_id = :period_id
            ORDER BY e.full_name ASC
        ");
        $stmt->execute(['period_id' => $periodId]);
        return $stmt->fetchAll() ?: [];
    }

    public function closePeriod(int $periodId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_payroll_periods
            SET status = 'closed', updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $periodId]);
    }
}
