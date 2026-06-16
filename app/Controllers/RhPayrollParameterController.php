<?php

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\PermissionMiddleware;
use App\Models\Database;
use App\Repositories\RhPayrollParameterRepository;
use App\Security\OperationPolicy;
use PDOException;

class RhPayrollParameterController extends BaseController
{
    private RhPayrollParameterRepository $repository;

    public function __construct()
    {
        $this->repository = new RhPayrollParameterRepository(Database::getConnection());
    }

    public function index(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_PARAMS_MANAGE);
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $data = $this->repository->paginate($page, 20);

        $this->view('rh/payroll_parameters/index', [
            'pageTitle' => 'Paramètres de Paie',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'payroll_params',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js', 'js/components.js'],
        ] + $data);
    }

    public function create(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_PARAMS_MANAGE);

        $currentYear = (int)date('Y');
        $defaultData = [
            'year' => $currentYear,
            'smig' => '75000',
            'cnps_ceiling' => '1647315',
            'cnps_employee_rate' => '3.2',
            'cnps_employer_rate' => '7.7',
            'cmu_employee_rate' => '2.0',
            'cmu_employer_rate' => '2.0',
            'cn_rate' => '1.5',
        ];

        // Pré-remplir avec l'année précédente si elle existe
        $previous = $this->repository->findByYear($currentYear - 1);
        if ($previous) {
            $defaultData = array_merge($defaultData, $previous);
            $defaultData['year'] = $currentYear;
        }

        $this->view('rh/payroll_parameters/form', [
            'pageTitle' => 'Nouveau Paramétrage',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'payroll_params',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js', 'js/components.js'],
            'param' => $defaultData,
            'formAction' => '/rh/parametres-paie',
            'submitLabel' => 'Enregistrer',
        ]);
    }

    public function store(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_PARAMS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/rh/parametres-paie/nouveau');
        }

        try {
            if ($this->repository->findByYear((int)$_POST['year'])) {
                Session::flash('error', "Un paramétrage existe déjà pour l'année {$_POST['year']}.");
                $this->redirect('/rh/parametres-paie/nouveau');
                return;
            }

            $this->repository->insert($_POST);
            Session::flash('success', 'Paramètres de paie enregistrés avec succès.');
            $this->redirect('/rh/parametres-paie');
        } catch (PDOException $e) {
            Session::flash('error', 'Erreur base de données : ' . $e->getMessage());
            $this->redirect('/rh/parametres-paie/nouveau');
        }
    }

    public function edit(int $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_PARAMS_MANAGE);

        $param = $this->repository->find($id);
        if (!$param) {
            $this->redirect('/rh/parametres-paie');
        }

        $this->view('rh/payroll_parameters/form', [
            'pageTitle' => 'Modifier Paramétrage',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'payroll_params',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js', 'js/components.js'],
            'param' => $param,
            'formAction' => "/rh/parametres-paie/{$id}/modifier",
            'submitLabel' => 'Mettre à jour',
        ]);
    }

    public function update(int $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_PAYROLL_PARAMS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect("/rh/parametres-paie/{$id}/modifier");
        }

        try {
            $existing = $this->repository->findByYear((int)$_POST['year']);
            if ($existing && $existing['id'] !== $id) {
                Session::flash('error', "Un paramétrage existe déjà pour l'année {$_POST['year']}.");
                $this->redirect("/rh/parametres-paie/{$id}/modifier");
                return;
            }

            $this->repository->update($id, $_POST);
            Session::flash('success', 'Paramètres de paie mis à jour.');
            $this->redirect('/rh/parametres-paie');
        } catch (PDOException $e) {
            Session::flash('error', 'Erreur base de données : ' . $e->getMessage());
            $this->redirect("/rh/parametres-paie/{$id}/modifier");
        }
    }
}
