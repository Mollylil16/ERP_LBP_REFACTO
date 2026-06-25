<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhPayrollRepository;
use App\View\Pages\Rh\PayrollIndexPage;
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
