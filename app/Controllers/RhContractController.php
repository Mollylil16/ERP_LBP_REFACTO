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
use PDO;

class RhContractController extends BaseController
{
    private RhContractService $service;
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->service = new RhContractService(new RhContractRepository($this->pdo));
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $this->view('rh/contracts/index', [
            'pageTitle' => 'Contrats & Rémunérations',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'contracts',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js', 'js/components.js', 'js/components/form-components.js'],
        ] + $this->service->list($_GET));
    }

    public function create(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_CONTRACT_MANAGE);
        
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $this->view('rh/contracts/form', [
            'pageTitle' => 'Nouveau Contrat',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'contracts',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js', 'js/components.js', 'js/components/form-components.js'],
            'contract' => ['employee_id' => $employeeId, 'contract_type' => 'CDI', 'status' => 'active'],
            'employees' => $this->getEmployeesOptions(),
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

        $this->view('rh/contracts/form', [
            'pageTitle' => 'Modifier Contrat',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'contracts',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js', 'js/components.js', 'js/components/form-components.js'],
            'contract' => $contract,
            'employees' => $this->getEmployeesOptions(),
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

        $this->view('rh/contracts/show', [
            'pageTitle' => 'Détails du Contrat',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'contracts',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css', 'css/components.css'],
            'additionalScripts' => ['js/rh.js'],
            'contract' => $contract,
        ]);
    }

    private function getEmployeesOptions(): array
    {
        $stmt = $this->pdo->query("SELECT id, CONCAT(employee_number, ' - ', full_name) as name FROM rh_employees ORDER BY full_name ASC");
        $options = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $options[$row['id']] = $row['name'];
        }
        return $options;
    }

    private function parseAllowances(array $post): array
    {
        $allowances = [];
        $names = $post['allowance_name'] ?? [];
        $amounts = $post['allowance_amount'] ?? [];
        $taxables = $post['allowance_taxable'] ?? [];

        foreach ($names as $i => $name) {
            if (empty(trim($name))) continue;
            $allowances[] = [
                'name' => trim($name),
                'amount' => (float)($amounts[$i] ?? 0),
                'is_taxable' => !empty($taxables[$i]) ? 1 : 0
            ];
        }
        return $allowances;
    }
}
