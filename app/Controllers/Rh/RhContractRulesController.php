<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhContractRulesRepository;
use App\View\Pages\Rh\ContractRulesPage;
use RuntimeException;

final class RhContractRulesController extends RhBaseController
{
    private RhContractRulesRepository $repository;

    public function __construct()
    {
        $this->repository = new RhContractRulesRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $rules = $this->repository->all();

        $this->rhView('rh/contract-rules/index', 'Regles automatiques des contrats', 'contract-rules', [
            'page' => new ContractRulesPage($rules),
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/regles-contrats');
        }
        try {
            $this->repository->save($_POST);
            Session::flash('success', 'Regle de contrat enregistree.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/rh/regles-contrats');
    }

    public function toggle(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/regles-contrats');
        }
        try {
            $this->repository->toggle((int)($_POST['id'] ?? 0));
            Session::flash('success', 'Statut de la regle de contrat mis a jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/rh/regles-contrats');
    }
}
