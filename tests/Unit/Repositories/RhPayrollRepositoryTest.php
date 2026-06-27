<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Rh\RhPayrollRepository;
use Tests\Support\DatabaseTestCase;

final class RhPayrollRepositoryTest extends DatabaseTestCase
{
    public function test_get_contract_rules_returns_active_rules(): void
    {
        $pdo = $this->database();
        $repository = new RhPayrollRepository($pdo);

        $rules = $repository->getContractRules();

        self::assertCount(2, $rules);
        self::assertSame('cdd', $rules[0]['contract_type']);
        self::assertSame('cdi_permanent', $rules[1]['contract_type']);
    }

    public function test_get_line_items_returns_active_items(): void
    {
        $pdo = $this->database();
        $repository = new RhPayrollRepository($pdo);

        $items = $repository->getLineItems();

        self::assertCount(2, $items);
        self::assertSame('alloc_assist_famille', $items[0]['code']);
        self::assertSame('prime_panier', $items[1]['code']);
    }

    public function test_get_payroll_settings_returns_first_row_or_defaults(): void
    {
        $pdo = $this->database();
        $repository = new RhPayrollRepository($pdo);

        $settings = $repository->getPayrollSettings();
        self::assertSame(1.2, (float)$settings['is_salarial_rate']);

        // Insert custom settings
        $pdo->exec("INSERT INTO rh_payroll_settings (id, is_salarial_rate) VALUES (2, 2.5)");
        $settings = $repository->getPayrollSettings();
        self::assertSame(1.2, (float)$settings['is_salarial_rate']);
    }

    public function test_save_contract_from_wizard_creates_or_updates(): void
    {
        $pdo = $this->database();
        $repository = new RhPayrollRepository($pdo);

        $id = $repository->saveContractFromWizard([
            'employee_id' => 1,
            'contract_type' => 'cdd',
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'base_salary' => 450000,
            'sursalaire' => 50000,
            'transport_locality' => 'ABIDJAN',
        ]);

        self::assertGreaterThan(0, $id);

        $row = $pdo->query("SELECT * FROM rh_contracts WHERE id = $id")->fetch();
        self::assertSame('cdd', $row['contract_type']);
        self::assertSame(450000.0, (float)$row['base_salary']);

        // Update existing contract
        $newId = $repository->saveContractFromWizard([
            'employee_id' => 1,
            'contract_type' => 'cdi_permanent',
            'start_date' => '2026-06-01',
            'base_salary' => 500000,
            'sursalaire' => 60000,
            'transport_locality' => 'ABIDJAN',
        ]);

        self::assertSame($id, $newId);
        $updatedRow = $pdo->query("SELECT * FROM rh_contracts WHERE id = $id")->fetch();
        self::assertSame('cdi_permanent', $updatedRow['contract_type']);
        self::assertSame(500000.0, (float)$updatedRow['base_salary']);
    }

    public function test_get_attendance_summaries_aggregates_records(): void
    {
        $pdo = $this->database();
        $repository = new RhPayrollRepository($pdo);

        $pdo->exec("INSERT INTO rh_attendance_daily (employee_id, attendance_date, attendance_status, worked_hours, overtime_hours) VALUES
            (1, '2026-06-01', 'present', 8, 2),
            (1, '2026-06-02', 'present', 8, 1),
            (1, '2026-06-03', 'absent', 0, 0),
            (2, '2026-06-01', 'present', 8, 0)
        ");

        $summaries = $repository->getAttendanceSummaries();

        self::assertCount(2, $summaries);
        
        $emp1 = array_values(array_filter($summaries, fn($s) => $s['employee_id'] == 1))[0];
        self::assertSame(2, (int)$emp1['count_present']);
        self::assertSame(1, (int)$emp1['count_absent']);
        self::assertSame(3.0, (float)$emp1['total_overtime']);
    }

    private function database(): \PDO
    {
        $pdo = $this->sqlite();
        
        // Register MySQL functions for SQLite
        $pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
        $pdo->sqliteCreateFunction('DATE_FORMAT', function($val, $format) {
            if (!$val) return null;
            $time = strtotime($val);
            if ($time === false) return null;
            $phpFormat = str_replace(['%Y', '%m', '%d'], ['Y', 'm', 'd'], $format);
            return date($phpFormat, $time);
        });

        $pdo->exec('CREATE TABLE rh_employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            employee_number TEXT NULL,
            is_active INTEGER NOT NULL DEFAULT 1
        )');

        $pdo->exec('CREATE TABLE rh_contracts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            contract_type TEXT NOT NULL,
            start_date TEXT NOT NULL,
            end_date TEXT NULL,
            base_salary REAL NULL,
            sursalaire REAL NULL,
            category TEXT NULL,
            transport_locality TEXT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NULL
        )');

        $pdo->exec('CREATE TABLE rh_payroll_contract_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            contract_type TEXT NOT NULL,
            label TEXT NOT NULL,
            working_days INTEGER NOT NULL DEFAULT 30,
            hours_per_day REAL NOT NULL DEFAULT 8.00,
            overtime_multiplier REAL NOT NULL DEFAULT 1.00,
            precarity_auto_rate REAL NOT NULL DEFAULT 0,
            mission_rate REAL NOT NULL DEFAULT 100,
            leave_rate REAL NOT NULL DEFAULT 100,
            half_day_rate REAL NOT NULL DEFAULT 50,
            absence_rate REAL NOT NULL DEFAULT 0,
            sickness_rate REAL NOT NULL DEFAULT 0,
            rest_rate REAL NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1
        )');

        $pdo->exec('CREATE TABLE rh_payroll_line_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            name TEXT NOT NULL,
            nature TEXT NOT NULL DEFAULT "gain",
            is_active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0
        )');

        $pdo->exec('CREATE TABLE rh_contract_line_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            contract_id INTEGER NOT NULL,
            line_item_id INTEGER NOT NULL,
            amount REAL NOT NULL DEFAULT 0
        )');

        $pdo->exec('CREATE TABLE rh_payroll_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            is_salarial_rate REAL NOT NULL DEFAULT 1.20,
            cnps_salarial_rate REAL NOT NULL DEFAULT 6.30,
            cnps_patronal_rate REAL NOT NULL DEFAULT 7.70,
            family_benefits_rate REAL NOT NULL DEFAULT 5.75,
            work_accident_rate REAL NOT NULL DEFAULT 5.00,
            apprentice_tax_rate REAL NOT NULL DEFAULT 0.40,
            professional_training_rate REAL NOT NULL DEFAULT 0.60,
            fdfp_rate REAL NOT NULL DEFAULT 0.60
        )');

        $pdo->exec('CREATE TABLE rh_attendance_daily (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            attendance_date TEXT NOT NULL,
            attendance_status TEXT NOT NULL,
            worked_hours REAL NOT NULL DEFAULT 8,
            overtime_hours REAL NOT NULL DEFAULT 0
        )');

        // Insert seed data
        $pdo->exec("INSERT INTO rh_employees (id, full_name, employee_number, is_active) VALUES 
            (1, 'John Doe', 'EMP001', 1),
            (2, 'Jane Smith', 'EMP002', 1)
        ");

        $pdo->exec("INSERT INTO rh_payroll_settings (id, is_salarial_rate) VALUES (1, 1.20)");

        $pdo->exec("INSERT INTO rh_payroll_contract_rules (contract_type, label, is_active) VALUES 
            ('cdd', 'CDD', 1),
            ('cdi_permanent', 'CDI permanent', 1),
            ('stage', 'Stage', 0)
        ");

        $pdo->exec("INSERT INTO rh_payroll_line_items (code, name, is_active, sort_order) VALUES 
            ('alloc_assist_famille', 'Allocations', 1, 10),
            ('prime_panier', 'Panier', 1, 20),
            ('prime_tenue', 'Tenue', 0, 30)
        ");

        return $pdo;
    }
}
