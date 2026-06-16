<?php

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\PermissionMiddleware;
use App\Models\Database;
use App\Repositories\RhAttendanceRepository;
use App\Services\RhAttendanceService;
use App\Security\OperationPolicy;
use PDO;

class RhAttendanceController extends BaseController
{
    private RhAttendanceService $service;
    private RhAttendanceRepository $repository;
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->repository = new RhAttendanceRepository($this->pdo);
        $this->service = new RhAttendanceService($this->repository, $this->pdo);
    }

    public function index(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_ATTENDANCE_MANAGE);
        
        $month = (int)($_GET['month'] ?? date('n'));
        $year = (int)($_GET['year'] ?? date('Y'));
        
        $attendances = $this->repository->getMonthAttendances($month, $year);

        $this->view('rh/attendance/index', [
            'pageTitle' => 'Pointage & Présences',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'attendance',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js', 'js/components.js'],
            'attendances' => $attendances,
            'month' => $month,
            'year' => $year,
        ]);
    }

    public function importForm(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_ATTENDANCE_MANAGE);

        $this->view('rh/attendance/import', [
            'pageTitle' => 'Importer Pointage',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'attendance',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
        ]);
    }

    public function import(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_ATTENDANCE_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/rh/pointage/import');
        }

        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Erreur lors du téléchargement du fichier.');
            $this->redirect('/rh/pointage/import');
        }

        try {
            $importedCount = $this->service->importFromCsv($file['tmp_name']);
            Session::flash('success', "Importation réussie. $importedCount enregistrements traités.");
            $this->redirect('/rh/pointage');
        } catch (\Exception $e) {
            Session::flash('error', 'Erreur d\'importation: ' . $e->getMessage());
            $this->redirect('/rh/pointage/import');
        }
    }

    public function create(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_ATTENDANCE_MANAGE);

        // Get list of active employees
        $stmt = $this->pdo->query("SELECT id, employee_number, full_name FROM rh_employees WHERE is_active = 1 ORDER BY full_name ASC");
        $employees = $stmt->fetchAll() ?: [];

        $this->view('rh/attendance/form', [
            'pageTitle' => 'Saisir Pointage',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'attendance',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'employees' => $employees,
        ]);
    }

    public function store(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_ATTENDANCE_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/rh/pointage');
        }

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $checkIn = $_POST['check_in'] ?? '';
        $checkOut = $_POST['check_out'] ?? '';
        $status = $_POST['status'] ?? 'present';

        if ($employeeId <= 0 || empty($date)) {
            Session::flash('error', 'Employé et Date requis.');
            $this->redirect('/rh/pointage/nouveau');
        }

        // Calculate hours
        $totalHours = 0;
        $overtimeHours = 0;

        if ($status === 'present' && $checkIn && $checkOut) {
            $in = strtotime($checkIn);
            $out = strtotime($checkOut);
            if ($out > $in) {
                $totalHours = round(($out - $in) / 3600, 2);
                if ($totalHours > 8) {
                    $overtimeHours = $totalHours - 8;
                    $totalHours = 8;
                }
            }
        }

        try {
            $this->repository->upsert([
                'employee_id' => $employeeId,
                'date' => $date,
                'check_in' => $checkIn ?: null,
                'check_out' => $checkOut ?: null,
                'total_hours' => $totalHours,
                'overtime_hours' => $overtimeHours,
                'status' => $status,
            ]);

            Session::flash('success', 'Pointage enregistré avec succès.');
            $this->redirect('/rh/pointage');
        } catch (\Exception $e) {
            Session::flash('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
            $this->redirect('/rh/pointage/nouveau');
        }
    }
}
