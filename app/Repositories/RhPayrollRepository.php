<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

class RhPayrollRepository
{
    public function __construct(private PDO $pdo) {}

    public function getCampaigns(): array
    {
        $stmt = $this->pdo->query("
            SELECT c.*, 
                   COUNT(p.id) as payslip_count,
                   SUM(p.net_salary) as total_net
            FROM rh_payroll_campaigns c
            LEFT JOIN rh_payslips p ON p.campaign_id = c.id
            GROUP BY c.id
            ORDER BY c.year DESC, c.month DESC
        ");
        return $stmt->fetchAll() ?: [];
    }

    public function createCampaign(int $month, int $year, int $actorId): int
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO rh_payroll_campaigns (month, year, status, created_by)
                VALUES (:month, :year, 'draft', :created_by)
            ");
            $stmt->execute([
                'month' => $month,
                'year' => $year,
                'created_by' => $actorId,
            ]);
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new RuntimeException("Une campagne existe déjà pour $month/$year.");
            }
            throw $e;
        }
    }

    public function getCampaign(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_payroll_campaigns WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getPayslips(int $campaignId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, e.employee_number, e.full_name
            FROM rh_payslips p
            INNER JOIN rh_employees e ON e.id = p.employee_id
            WHERE p.campaign_id = :campaign_id
            ORDER BY e.full_name ASC
        ");
        $stmt->execute(['campaign_id' => $campaignId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getPayslip(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, e.employee_number, e.full_name, e.service_id, e.function_id, e.marital_status, e.children_count, e.hire_date,
                   c.month, c.year, s.name as service_name, f.name as function_name
            FROM rh_payslips p
            INNER JOIN rh_employees e ON e.id = p.employee_id
            INNER JOIN rh_payroll_campaigns c ON c.id = p.campaign_id
            LEFT JOIN rh_services s ON s.id = e.service_id
            LEFT JOIN rh_functions f ON f.id = e.function_id
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $payslip = $stmt->fetch();
        if (!$payslip) return null;

        $lineStmt = $this->pdo->prepare("SELECT * FROM rh_payslip_lines WHERE payslip_id = :payslip_id ORDER BY id ASC");
        $lineStmt->execute(['payslip_id' => $id]);
        $payslip['lines'] = $lineStmt->fetchAll() ?: [];

        return $payslip;
    }

    public function savePayslip(int $campaignId, int $employeeId, array $data): void
    {
        $this->pdo->beginTransaction();
        try {
            // Delete if exists
            $del = $this->pdo->prepare("DELETE FROM rh_payslips WHERE campaign_id = :cid AND employee_id = :eid");
            $del->execute(['cid' => $campaignId, 'eid' => $employeeId]);

            $stmt = $this->pdo->prepare("
                INSERT INTO rh_payslips (
                    employee_id, campaign_id, base_salary, overtime_pay, total_allowances, gross_salary,
                    cnps_deduction, cmu_deduction, its_deduction, net_salary
                ) VALUES (
                    :employee_id, :campaign_id, :base_salary, :overtime_pay, :total_allowances, :gross_salary,
                    :cnps_deduction, :cmu_deduction, :its_deduction, :net_salary
                )
            ");
            $stmt->execute([
                'employee_id' => $employeeId,
                'campaign_id' => $campaignId,
                'base_salary' => $data['base_salary'],
                'overtime_pay' => $data['overtime_pay'],
                'total_allowances' => $data['total_allowances'],
                'gross_salary' => $data['gross_salary'],
                'cnps_deduction' => $data['cnps_deduction'],
                'cmu_deduction' => $data['cmu_deduction'],
                'its_deduction' => $data['its_deduction'],
                'net_salary' => $data['net_salary'],
            ]);
            $payslipId = (int)$this->pdo->lastInsertId();

            $lineStmt = $this->pdo->prepare("
                INSERT INTO rh_payslip_lines (payslip_id, type, label, base, rate, amount, is_taxable)
                VALUES (:payslip_id, :type, :label, :base, :rate, :amount, :is_taxable)
            ");
            foreach ($data['lines'] as $line) {
                $lineStmt->execute([
                    'payslip_id' => $payslipId,
                    'type' => $line['type'],
                    'label' => $line['label'],
                    'base' => $line['base'],
                    'rate' => $line['rate'],
                    'amount' => $line['amount'],
                    'is_taxable' => $line['is_taxable'] ?? 0,
                ]);
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
