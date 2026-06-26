<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhMissionRepository;
use App\View\Pages\Rh\MissionIndexPage;
use RuntimeException;

final class RhMissionController extends RhBaseController
{
    private RhMissionRepository $repository;

    public function __construct()
    {
        $this->repository = new RhMissionRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $missions = $this->repository->all();
        $employees = $this->repository->getActiveEmployees();

        // Apply filters in PHP
        $search = trim((string)($_GET['search'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        if ($search !== '' || ($status !== '' && $status !== 'Tous')) {
            $filtered = [];
            foreach ($missions as $m) {
                // Match status
                if ($status !== '' && $status !== 'Tous' && $m['status'] !== $status) {
                    continue;
                }
                // Match search term
                if ($search !== '') {
                    $searchLower = mb_strtolower($search);
                    $destMatch = mb_strpos(mb_strtolower((string)$m['destination']), $searchLower) !== false;
                    $empMatch = mb_strpos(mb_strtolower((string)$m['employee_name']), $searchLower) !== false;
                    $purposeMatch = mb_strpos(mb_strtolower((string)$m['purpose']), $searchLower) !== false;
                    $codeMatch = mb_strpos(mb_strtolower('om-' . $m['id']), $searchLower) !== false;
                    if (!$destMatch && !$empMatch && !$purposeMatch && !$codeMatch) {
                        continue;
                    }
                }
                $filtered[] = $m;
            }
            $missions = $filtered;
        }

        $this->rhView('rh/missions/index', 'Ordres de mission', 'missions', [
            'page' => new MissionIndexPage($missions, $employees),
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::check();
        $employees = $this->repository->getActiveEmployees();

        $this->rhView('rh/missions/form', 'Creer un ordre de mission', 'missions', [
            'employees' => $employees,
            'mission' => null,
        ]);
    }

    public function edit(string $id): void
    {
        AuthMiddleware::check();
        $employees = $this->repository->getActiveEmployees();
        $mission = $this->repository->find((int) $id);

        if (!$mission) {
            Session::flash('error', 'Ordre de mission introuvable.');
            $this->redirect('/rh/missions');
        }

        $this->rhView('rh/missions/form', 'Modifier l\'ordre de mission', 'missions', [
            'employees' => $employees,
            'mission' => $mission,
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/missions');
        }

        try {
            $this->repository->save($_POST);
            Session::flash('success', 'Ordre de mission enregistre.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/missions');
    }

    public function decide(string $id): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/missions');
        }

        try {
            $status = (string) ($_POST['status'] ?? '');
            $this->repository->decide((int) $id, $status, (int) Auth::id());
            Session::flash('success', 'Statut de la mission mis a jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/missions');
    }
}
