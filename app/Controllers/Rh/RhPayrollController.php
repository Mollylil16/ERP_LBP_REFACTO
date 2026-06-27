<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhPayrollRepository;
use App\View\Pages\Rh\PayrollIndexPage;
use App\View\Pages\Rh\PayrollWizardPage;
use RuntimeException;

final class RhPayrollController extends RhBaseController
{
    private RhPayrollRepository $repository;

    public function __construct()
    {
        $this->repository = new RhPayrollRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $periods = $this->repository->getPeriods();
        $activePeriodId = (int) ($_GET['period_id'] ?? 0);

        if ($activePeriodId <= 0 && count($periods) > 0) {
            $activePeriodId = (int) $periods[0]['id'];
        }

        $variables = $activePeriodId > 0 ? $this->repository->getVariables($activePeriodId) : [];
        $slips = $activePeriodId > 0 ? $this->repository->getSlips($activePeriodId) : [];

        $this->rhView('rh/payroll/index', 'Gestion de la Paie', 'payroll', [
            'page' => new PayrollIndexPage($periods, $activePeriodId, $variables, $slips),
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::check();
        
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT id, full_name, employee_number FROM rh_employees WHERE is_active = 1 ORDER BY full_name ASC");
        $employees = $stmt->fetchAll() ?: [];

        $periods = $this->repository->getPeriods();
        $contractRules = $this->repository->getContractRules();
        $lineItems = $this->repository->getLineItems();
        $payrollSettings = $this->repository->getPayrollSettings();
        $employeeContracts = $this->repository->getEmployeeContracts();
        $attendanceSummaries = $this->repository->getAttendanceSummaries();

        $this->rhView('rh/payroll/create', 'Nouvelle Fiche de Paie', 'payroll', [
            'page' => new PayrollWizardPage(
                employees: $employees,
                periods: $periods,
                contractRules: $contractRules,
                lineItems: $lineItems,
                payrollSettings: $payrollSettings,
                employeeContracts: $employeeContracts,
                attendanceSummaries: $attendanceSummaries,
            ),
        ]);
    }

    public function storeContract(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/rh/paie/nouveau');
        }

        try {
            $this->repository->saveContractFromWizard($_POST);
            Session::flash('success', 'Contrat RH enregistré avec succès.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/paie/nouveau');
    }

    public function storeWizard(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/rh/paie');
        }

        try {
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $baseSalary = (float) ($_POST['base_salary'] ?? 0);
            $bonusesTotal = (float) ($_POST['bonuses_total'] ?? 0);
            $deductionsTotal = (float) ($_POST['deductions_total'] ?? 0);
            $netSalary = (float) ($_POST['net_salary'] ?? 0);

            $transportPremium = (float) ($_POST['transport_premium'] ?? 0.0);
            $healthInsurance = (float) ($_POST['health_insurance'] ?? 0.0);
            $advanceDeduction = (float) ($_POST['advance_deduction'] ?? 0.0);
            $otherDeductions = (float) ($_POST['other_deductions'] ?? 0.0);
            $rounding = (float) ($_POST['rounding'] ?? 0.0);
            $observations = isset($_POST['observations']) ? trim((string) $_POST['observations']) : null;
            $fiscalParts = (int) ($_POST['fiscal_parts'] ?? 1);
            $igrManual = (float) ($_POST['igr_manual'] ?? 0.0);

            $pdo = Database::getConnection();

            // Resolve period_id from period_month (e.g. 2026-06)
            $periodMonth = trim((string) ($_POST['period_month'] ?? ''));
            $periodId = 0;
            if ($periodMonth !== '') {
                $stmtPeriod = $pdo->prepare("SELECT id FROM rh_payroll_periods WHERE code = ?");
                $stmtPeriod->execute([$periodMonth]);
                $periodId = (int) $stmtPeriod->fetchColumn();
            }
            if ($periodId <= 0) {
                $periodId = (int) ($_POST['period_id'] ?? 0);
            }

            if ($employeeId <= 0 || $periodId <= 0 || $baseSalary <= 0) {
                throw new \RuntimeException('Le salarié, la période de paie valide (ouverte) et le salaire de base sont obligatoires.');
            }

            // Delete existing slip for this employee and period if any
            $del = $pdo->prepare("DELETE FROM rh_payroll_slips WHERE period_id = ? AND employee_id = ?");
            $del->execute([$periodId, $employeeId]);

            // Save slip to database with all detailed fields
            $stmt = $pdo->prepare("
                INSERT INTO rh_payroll_slips (
                    period_id, employee_id, base_salary, bonuses_total, deductions_total, net_salary, status,
                    transport_premium, health_insurance, advance_deduction, other_deductions, rounding, observations,
                    fiscal_parts, igr_manual, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 'draft',
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, NOW(), NOW()
                )
            ");
            $stmt->execute([
                $periodId, $employeeId, $baseSalary, $bonusesTotal, $deductionsTotal, $netSalary,
                $transportPremium, $healthInsurance, $advanceDeduction, $otherDeductions, $rounding, $observations,
                $fiscalParts, $igrManual
            ]);

            // Save variables for this period
            $check = $pdo->prepare("SELECT id FROM rh_payroll_variables WHERE period_id = ? AND employee_id = ?");
            $check->execute([$periodId, $employeeId]);
            
            $workedDays = (float) ($_POST['worked_days'] ?? 30.0);
            $overtimeHours = (float) ($_POST['overtime_hours'] ?? 0.0);
            
            if ($check->fetch()) {
                $up = $pdo->prepare("UPDATE rh_payroll_variables SET worked_days = ?, overtime_hours = ?, bonus = ?, deductions = ?, updated_at = NOW() WHERE period_id = ? AND employee_id = ?");
                $up->execute([$workedDays, $overtimeHours, $bonusesTotal, $deductionsTotal, $periodId, $employeeId]);
            } else {
                $ins = $pdo->prepare("INSERT INTO rh_payroll_variables (period_id, employee_id, worked_days, absences_days, overtime_hours, bonus, deductions, created_at, updated_at) VALUES (?, ?, ?, 0, ?, ?, ?, NOW(), NOW())");
                $ins->execute([$periodId, $employeeId, $workedDays, $overtimeHours, $bonusesTotal, $deductionsTotal]);
            }

            Session::flash('success', 'Bulletin de salaire généré avec succès.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/paie?period_id=' . $periodId);
    }

    public function storePeriod(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/paie');
        }

        try {
            $code = trim((string) ($_POST['code'] ?? ''));
            $start = trim((string) ($_POST['start_date'] ?? ''));
            $end = trim((string) ($_POST['end_date'] ?? ''));

            if ($code === '' || $start === '' || $end === '') {
                throw new RuntimeException('Tous les champs sont obligatoires.');
            }

            $this->repository->createPeriod($code, $start, $end);
            Session::flash('success', 'Periode de paie creee.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/paie');
    }

    public function storeVariables(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/paie');
        }

        $periodId = (int) ($_POST['period_id'] ?? 0);
        $variables = $_POST['records'] ?? [];

        try {
            $this->repository->saveVariables($periodId, $variables);
            Session::flash('success', 'Variables de paie enregistrees.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/paie?period_id=' . $periodId);
    }

    public function calculate(string $id): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/paie');
        }

        try {
            $this->repository->calculateSlips((int) $id);
            Session::flash('success', 'Calcul des bulletins lance avec succes.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/paie?period_id=' . $id);
    }

    public function close(string $id): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/paie');
        }

        try {
            $this->repository->closePeriod((int) $id);
            Session::flash('success', 'Periode de paie cloturee definitivement.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/paie?period_id=' . $id);
    }
}
