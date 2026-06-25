<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhSignatoryRepository;
use App\View\Pages\Rh\SignatoryIndexPage;
use RuntimeException;

final class RhSignatoryController extends RhBaseController
{
    private RhSignatoryRepository $repository;

    public function __construct()
    {
        $this->repository = new RhSignatoryRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $signatories = $this->repository->all();
        $employees = $this->repository->getActiveEmployees();

        $this->rhView('rh/signatories/index', 'Signataires RH habilites', 'signatories', [
            'page' => new SignatoryIndexPage($signatories, $employees),
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/signataires');
        }
        try {
            $this->repository->save($_POST);
            Session::flash('success', 'Signataire RH enregistre.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/rh/signataires');
    }

    public function toggle(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/signataires');
        }
        try {
            $this->repository->toggle((int)($_POST['id'] ?? 0));
            Session::flash('success', 'Statut du signataire mis a jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/rh/signataires');
    }
}
