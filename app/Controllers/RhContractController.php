<?php

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\PermissionMiddleware;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\RhContractRepository;
use App\Services\RhContractService;
use App\Security\OperationPolicy;
use RuntimeException;

class RhContractController extends BaseController
{
    private RhContractService $service;

    public function __construct()
    {
        $this->service = new RhContractService(new RhContractRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $this->view('rh/contracts/index', $this->viewData('Contrats & Rémunérations') + $this->service->list($_GET));
    }

    public function create(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_CONTRACT_MANAGE);
        
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $this->view('rh/contracts/form', $this->viewData('Nouveau Contrat') + [
            'pageTitle' => 'Nouveau Contrat',
            'contract' => ['employee_id' => $employeeId, 'contract_type' => 'CDI', 'status' => 'active'],
            'employees' => $this->service->employeeOptions(),
            'formAction' => '/rh/contrats',
            'submitLabel' => 'Enregistrer le contrat',
        ]);
    }

    public function store(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_CONTRACT_MANAGE);
        
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/rh/contrats/nouveau');
        }

        try {
            $allowances = $this->parseAllowances($_POST);
            $this->service->save($_POST, $allowances);
            Session::flash('success', 'Le contrat a été enregistré.');
            $this->redirect('/rh/contrats');
        } catch (RuntimeException | \InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/rh/contrats/nouveau');
        }
    }

    public function edit(int $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_CONTRACT_MANAGE);
        
        $contract = $this->service->get($id);
        if (!$contract) {
            $this->redirect('/rh/contrats');
        }

        $this->view('rh/contracts/form', $this->viewData('Modifier Contrat') + [
            'contract' => $contract,
            'employees' => $this->service->employeeOptions(),
            'formAction' => "/rh/contrats/{$id}/modifier",
            'submitLabel' => 'Mettre à jour',
        ]);
    }

    public function update(int $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_CONTRACT_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect("/rh/contrats/{$id}/modifier");
        }

        try {
            $_POST['id'] = $id;
            $allowances = $this->parseAllowances($_POST);
            $this->service->save($_POST, $allowances);
            Session::flash('success', 'Contrat mis à jour avec succès.');
            $this->redirect('/rh/contrats');
        } catch (RuntimeException | \InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("/rh/contrats/{$id}/modifier");
        }
    }

    public function show(int $id): void
    {
        AuthMiddleware::check();
        $contract = $this->service->get($id);
        if (!$contract) {
            $this->redirect('/rh/contrats');
        }

        $this->view('rh/contracts/show', $this->viewData('Détails du Contrat') + [
            'contract' => $contract,
        ]);
    }

    private function viewData(string $pageTitle): array
    {
        require BASE_PATH . '/views/rh/_navigation.php';

        return [
            'pageTitle' => $pageTitle,
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'contracts',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css'],
            'additionalScripts' => ['js/rh.js'],
            'moduleNavigation' => $moduleNavigation,
        ];
    }

    private function parseAllowances(array $post): array
    {
        $allowances = [];
        $names = $post['allowance_name'] ?? [];
        $amounts = $post['allowance_amount'] ?? [];
        $taxables = $post['allowance_taxable'] ?? [];

        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $allowances[] = [
                'name' => $name,
                'amount' => (float)($amounts[$i] ?? 0),
                'is_taxable' => !empty($taxables[$i]) ? 1 : 0
            ];
        }
        return $allowances;
    }
}
