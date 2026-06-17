<?php

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\PermissionMiddleware;
use App\Models\Database;
use App\Repositories\RhPayrollRepository;
use App\Repositories\RhPayrollParameterRepository;
use App\Repositories\RhContractRepository;
use App\Repositories\RhAttendanceRepository;
use App\Services\RhPayrollEngine;
use App\Security\OperationPolicy;
use PDO;

class RhPayrollController extends BaseController
{
    private PDO $pdo;
    private RhPayrollRepository $repository;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->repository = new RhPayrollRepository($this->pdo);
    }

    public function index(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_MANAGE);
        
        $campaigns = $this->repository->getCampaigns();

        $this->view('rh/payroll/campaigns', [
            'pageTitle' => 'Gestion de la Paie',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'payroll',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js', 'js/components.js'],
            'campaigns' => $campaigns,
        ]);
    }

    public function createCampaign(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_MANAGE);
        
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/rh/paie');
        }

        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);

        if ($month < 1 || $month > 12 || $year < 2020) {
            Session::flash('error', 'Mois ou année invalide.');
            $this->redirect('/rh/paie');
        }

        try {
            $actorId = $_SESSION['user_id'] ?? 1;
            $this->repository->createCampaign($month, $year, $actorId);
            Session::flash('success', "Campagne de paie $month/$year créée avec succès.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/paie');
    }

    public function generate(int $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/rh/paie');
        }

        $campaign = $this->repository->getCampaign($id);
        if (!$campaign) {
            Session::flash('error', 'Campagne introuvable.');
            $this->redirect('/rh/paie');
        }

        $month = (int)$campaign['month'];
        $year = (int)$campaign['year'];

        // Repositories needed for generation
        $paramRepo = new RhPayrollParameterRepository($this->pdo);
        $contractRepo = new RhContractRepository($this->pdo);
        $attendanceRepo = new RhAttendanceRepository($this->pdo);
        $engine = new RhPayrollEngine();

        $params = $paramRepo->findByYear($year);
        if (!$params) {
            Session::flash('error', "Impossible de générer : Paramètres légaux manquants pour l'année $year.");
            $this->redirect('/rh/paie');
        }

        $attendances = $attendanceRepo->getMonthAttendances($month, $year);
        // Group attendances by employee
        $attByEmp = [];
        foreach ($attendances as $att) {
            $attByEmp[$att['employee_id']][] = $att;
        }

        // Get active employees with active contracts
        $stmt = $this->pdo->query("SELECT * FROM rh_employees WHERE is_active = 1");
        $employees = $stmt->fetchAll();

        $generatedCount = 0;
        foreach ($employees as $employee) {
            $empId = (int)$employee['id'];
            $contract = $contractRepo->findActiveByEmployee($empId);
            if (!$contract) continue; // No active contract, skip payroll

            $allowances = $contract['allowances'] ?? [];
            $empAttendances = $attByEmp[$empId] ?? [];

            $payslipData = $engine->calculate($employee, $contract, $allowances, $params, $empAttendances);
            $this->repository->savePayslip($id, $empId, $payslipData);
            $generatedCount++;
        }

        Session::flash('success', "Génération terminée. $generatedCount bulletins calculés.");
        $this->redirect('/rh/paie/campagnes/' . $id . '/bulletins');
    }

    public function showCampaignPayslips(int $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_MANAGE);

        $campaign = $this->repository->getCampaign($id);
        if (!$campaign) {
            Session::flash('error', 'Campagne introuvable.');
            $this->redirect('/rh/paie');
        }

        $payslips = $this->repository->getPayslips($id);

        $this->view('rh/payroll/campaign_payslips', [
            'pageTitle' => 'Bulletins de Paie - ' . $campaign['month'] . '/' . $campaign['year'],
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'payroll',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'payslips' => $payslips,
            'campaign' => $campaign
        ]);
    }

    public function showPayslip(int $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_MANAGE);

        $payslip = $this->repository->getPayslip($id);
        if (!$payslip) {
            Session::flash('error', 'Bulletin introuvable.');
            $this->redirect('/rh/paie');
        }

        $this->view('rh/payroll/payslip', [
            'pageTitle' => 'Bulletin de Paie',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'payroll',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'payslip' => $payslip,
        ]);
    }
}
