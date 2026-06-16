<?php

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\PermissionMiddleware;
use App\Models\Database;
use App\Repositories\RhLeaveRepository;
use App\Repositories\RhPersonnelRepository;
use App\Helpers\Auth;
use App\Security\OperationPolicy;
use App\Security\PermissionEntityRegistry;
use PDO;

class RhLeaveController extends BaseController
{
    private RhLeaveRepository $leaveRepo;
    private RhPersonnelRepository $personnelRepo;
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->leaveRepo = new RhLeaveRepository($this->pdo);
        $this->personnelRepo = new RhPersonnelRepository($this->pdo);
    }

    public function index(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_LEAVES_REQUEST);
        
        $actorId = Auth::id() ?? 1;
        $isHR = Auth::can(PermissionEntityRegistry::RH_LEAVES, 'update');

        $employee = $this->personnelRepo->findById($actorId); // Assuming user_id maps to employee_id for this demo

        if ($isHR) {
            $requests = $this->leaveRepo->getAllRequests();
            $balance = null;
        } else {
            if (!$employee) {
                Session::flash('error', 'Profil employé introuvable.');
                $this->redirect('/rh/dashboard');
            }
            $requests = $this->leaveRepo->getEmployeeRequests((int)$employee['id']);
            $balance = $this->leaveRepo->calculateBalance((int)$employee['id'], $employee['hire_date']);
        }

        $this->view('rh/leaves/index', [
            'pageTitle' => 'Congés & Absences',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'leaves',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js', 'js/components.js'],
            'requests' => $requests,
            'isHR' => $isHR,
            'balance' => $balance,
            'employee' => $employee,
        ]);
    }

    public function create(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_LEAVES_REQUEST);

        $types = $this->leaveRepo->getLeaveTypes();
        
        $actorId = Auth::id() ?? 1;
        $isHR = Auth::can(PermissionEntityRegistry::RH_LEAVES, 'update');

        // Si RH, il peut sélectionner l'employé
        $employees = $isHR ? $this->personnelRepo->getAll() : [];

        $this->view('rh/leaves/form', [
            'pageTitle' => 'Nouvelle Demande',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'leaves',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'leaveTypes' => $types,
            'isHR' => $isHR,
            'employees' => $employees,
            'actorId' => $actorId,
        ]);
    }

    public function store(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_LEAVES_REQUEST);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/rh/conges/nouveau');
        }

        $actorId = $_SESSION['user_id'] ?? 1;
        $employeeId = (int)($_POST['employee_id'] ?? $actorId);
        
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        
        if (empty($startDate) || empty($endDate) || strtotime($endDate) < strtotime($startDate)) {
            Session::flash('error', 'Dates invalides.');
            $this->redirect('/rh/conges/nouveau');
        }

        // Simplistic days calculation excluding weekends could go here. For now: diff days.
        $d1 = new \DateTime($startDate);
        $d2 = new \DateTime($endDate);
        $durationDays = $d1->diff($d2)->days + 1;

        $data = [
            'employee_id' => $employeeId,
            'leave_type_id' => (int)$_POST['leave_type_id'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration_days' => $durationDays,
            'reason' => $_POST['reason'] ?? '',
        ];

        try {
            $this->leaveRepo->createRequest($data);
            Session::flash('success', 'Demande soumise avec succès.');
        } catch (\Exception $e) {
            Session::flash('error', 'Erreur : ' . $e->getMessage());
        }

        $this->redirect('/rh/conges');
    }

    public function approve(int $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_LEAVES_MANAGE);
        
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/rh/conges');
        }

        $actorId = $_SESSION['user_id'] ?? 1;
        $this->leaveRepo->updateStatus($id, 'approved', $actorId);
        Session::flash('success', 'Demande approuvée.');
        $this->redirect('/rh/conges');
    }

    public function reject(int $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_LEAVES_MANAGE);
        
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/rh/conges');
        }

        $actorId = $_SESSION['user_id'] ?? 1;
        $this->leaveRepo->updateStatus($id, 'rejected', $actorId);
        Session::flash('success', 'Demande refusée.');
        $this->redirect('/rh/conges');
    }
}
