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

    /** @return array<int,array<string,mixed>> */
    public function getContractRules(): array
    {
        return $this->pdo->query("
            SELECT * FROM rh_payroll_contract_rules
            WHERE is_active = 1
            ORDER BY id ASC
        ")->fetchAll() ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function getLineItems(): array
    {
        return $this->pdo->query("
            SELECT * FROM rh_payroll_line_items
            WHERE is_active = 1
            ORDER BY sort_order ASC
        ")->fetchAll() ?: [];
    }

    /** @return array<string,mixed> */
    public function getPayrollSettings(): array
    {
        $row = $this->pdo->query("SELECT * FROM rh_payroll_settings LIMIT 1")->fetch();
        return $row ?: [
            'is_salarial_rate' => 1.2,
            'cnps_salarial_rate' => 6.3,
            'cnps_patronal_rate' => 7.7,
            'family_benefits_rate' => 5.75,
            'work_accident_rate' => 5.0,
            'apprentice_tax_rate' => 0.4,
            'professional_training_rate' => 0.6,
            'fdfp_rate' => 0.6,
        ];
    }

    /**
     * Retourne les contrats actifs indexés par employee_id, avec leurs line items.
     * @return array<int,array<string,mixed>>
     */
    public function getEmployeeContracts(): array
    {
        $contracts = $this->pdo->query("
            SELECT c.*, e.full_name
            FROM rh_contracts c
            INNER JOIN rh_employees e ON e.id = c.employee_id
            WHERE c.status = 'active'
            ORDER BY c.start_date DESC
        ")->fetchAll() ?: [];

        $lineItemStmt = $this->pdo->prepare("
            SELECT cli.line_item_id, cli.amount, pli.code, pli.name, pli.nature
            FROM rh_contract_line_items cli
            INNER JOIN rh_payroll_line_items pli ON pli.id = cli.line_item_id
            WHERE cli.contract_id = :contract_id
        ");

        $result = [];
        foreach ($contracts as $c) {
            $empId = (int) $c['employee_id'];
            $lineItemStmt->execute(['contract_id' => (int) $c['id']]);
            $c['line_items'] = $lineItemStmt->fetchAll() ?: [];
            $result[$empId] = $c;
        }

        return $result;
    }

    /**
     * Retourne les résumés de pointage mensuels par employé.
     * @return array<int,array<string,mixed>>
     */
    public function getAttendanceSummaries(): array
    {
        return $this->pdo->query("
            SELECT 
                employee_id,
                DATE_FORMAT(attendance_date, '%Y-%m') as month_code,
                SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) as count_present,
                SUM(CASE WHEN attendance_status = 'absent' THEN 1 ELSE 0 END) as count_absent,
                SUM(CASE WHEN attendance_status = 'half_day' THEN 1 ELSE 0 END) as count_half_day,
                SUM(CASE WHEN attendance_status = 'mission' THEN 1 ELSE 0 END) as count_mission,
                SUM(CASE WHEN attendance_status = 'conge' THEN 1 ELSE 0 END) as count_conge,
                SUM(CASE WHEN attendance_status = 'rest' THEN 1 ELSE 0 END) as count_rest,
                SUM(overtime_hours) as total_overtime
            FROM rh_attendance_daily
            GROUP BY employee_id, DATE_FORMAT(attendance_date, '%Y-%m')
        ")->fetchAll() ?: [];
    }

    /**
     * Crée ou met à jour un contrat RH depuis le wizard de paie.
     */
    public function saveContractFromWizard(array $data): int
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $contractType = trim((string) ($data['contract_type'] ?? 'libre'));
        $startDate = trim((string) ($data['start_date'] ?? date('Y-m-d')));
        $endDate = !empty($data['end_date']) ? trim((string) $data['end_date']) : null;
        $baseSalary = (float) ($data['base_salary'] ?? 0);
        $sursalaire = (float) ($data['sursalaire'] ?? 0);
        $transportLocality = trim((string) ($data['transport_locality'] ?? ''));

        if ($employeeId <= 0) {
            throw new \RuntimeException('L\'employé est obligatoire.');
        }

        // Check for existing active contract
        $existing = $this->pdo->prepare("
            SELECT id FROM rh_contracts
            WHERE employee_id = :emp AND status = 'active'
            LIMIT 1
        ");
        $existing->execute(['emp' => $employeeId]);
        $existingRow = $existing->fetch();

        if ($existingRow) {
            $stmt = $this->pdo->prepare("
                UPDATE rh_contracts
                SET contract_type = :type, start_date = :start, end_date = :end,
                    base_salary = :salary, sursalaire = :sur, transport_locality = :transport,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'type' => $contractType,
                'start' => $startDate,
                'end' => $endDate,
                'salary' => $baseSalary,
                'sur' => $sursalaire,
                'transport' => $transportLocality,
                'id' => (int) $existingRow['id'],
            ]);
            return (int) $existingRow['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO rh_contracts
                (employee_id, contract_type, start_date, end_date, base_salary, sursalaire,
                 transport_locality, status, created_at)
            VALUES (:emp, :type, :start, :end, :salary, :sur, :transport, 'active', NOW())
        ");
        $stmt->execute([
            'emp' => $employeeId,
            'type' => $contractType,
            'start' => $startDate,
            'end' => $endDate,
            'salary' => $baseSalary,
            'sur' => $sursalaire,
            'transport' => $transportLocality,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
