<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhAttendanceRepository;
use App\View\Pages\Rh\AttendanceDailyPage;
use App\View\Pages\Rh\AttendanceMonthlyPage;
use RuntimeException;

final class RhAttendanceController extends RhBaseController
{
    private RhAttendanceRepository $repository;

    public function __construct()
    {
        $this->repository = new RhAttendanceRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $vue = (string) ($_GET['vue'] ?? 'journalier');

        if ($vue === 'mensuel') {
            $employeeId = (int) ($_GET['employee_id'] ?? 0);
            $month = (string) ($_GET['month'] ?? date('Y-m'));

            $employees = $this->repository->getActiveEmployees();
            if ($employeeId <= 0 && count($employees) > 0) {
                $employeeId = (int) $employees[0]['id'];
            }

            $records = $employeeId > 0 ? $this->repository->getMonthlyAttendance($employeeId, $month) : [];

            $this->rhView('rh/attendance/monthly', 'Pointage mensuel', 'attendance', [
                'page' => new AttendanceMonthlyPage($employeeId, $month, $records, $employees),
            ]);
            return;
        }

        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        $records = $this->repository->getDailyAttendance($date);

        $this->rhView('rh/attendance/daily', 'Pointage journalier', 'attendance', [
            'page' => new AttendanceDailyPage($date, $records),
        ]);
    }

    public function storeDaily(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/pointage');
        }

        $date = (string) ($_POST['date'] ?? date('Y-m-d'));
        $records = $_POST['records'] ?? [];

        try {
            $this->repository->saveDailyAttendance($date, $records);
            Session::flash('success', 'Pointages enregistres avec succes.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/pointage?date=' . urlencode($date));
    }
}
