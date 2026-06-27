<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhHolidayRepository;
use App\View\Pages\Rh\HolidayIndexPage;
use RuntimeException;

final class RhHolidayController extends RhBaseController
{
    private RhHolidayRepository $repository;

    public function __construct()
    {
        $this->repository = new RhHolidayRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $selectedMonth = (string) ($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = date('Y-m');
        }

        $holidays = $this->repository->all();

        $this->rhView('rh/holidays/index', 'Gestion des feries', 'holidays', [
            'page' => new HolidayIndexPage($holidays, $selectedMonth),
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/feries');
        }
        try {
            $this->repository->save($_POST);
            Session::flash('success', 'Jour ferie enregistre.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $month = trim((string)($_POST['month'] ?? ''));
        $redirectUrl = '/rh/feries';
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $redirectUrl .= '?month=' . urlencode($month);
        }
        $this->redirect($redirectUrl);
    }

    public function toggle(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/feries');
        }
        try {
            $this->repository->toggle((int)($_POST['id'] ?? 0));
            Session::flash('success', 'Statut du jour ferie mis a jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $month = trim((string)($_POST['month'] ?? ''));
        $redirectUrl = '/rh/feries';
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $redirectUrl .= '?month=' . urlencode($month);
        }
        $this->redirect($redirectUrl);
    }
}
