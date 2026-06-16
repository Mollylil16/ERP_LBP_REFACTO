<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Repositories\CrmRepository;
use App\Security\OperationPolicy;
use App\Services\BusinessModuleService;

final class CrmController extends BaseController
{
    private CrmRepository $crmRepo;

    public function __construct()
    {
        $this->crmRepo = new CrmRepository();
    }

    public function dashboard(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::CRM_CLIENTS_VIEW);
        
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->crmDashboard();
        $this->view('modules/dashboard', ['pageTitle'=>'Tableau de bord CRM','moduleName'=>'CRM','moduleCode'=>'CRM','moduleTheme'=>$module,'activeModule'=>'dashboard','moduleNavigation'=>$module['navigation'],'dashboardModule'=>$module,'additionalStyles'=>['css/finea-ui.css']]);
    }

    public function clients(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::CRM_CLIENTS_VIEW);
        
        $clients = $this->crmRepo->getAllClients();
        
        $this->view('crm/clients/index', $this->viewData('Clients (Expéditeurs / Destinataires)', 'clients') + [
            'clients' => $clients
        ]);
    }

    private function viewData(string $title, string $active): array
    {
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->crmDashboard();
        return [
            'pageTitle' => $title,
            'moduleName' => 'CRM',
            'moduleCode' => 'CRM',
            'moduleTheme' => $module,
            'activeModule' => $active,
            'moduleNavigation' => $module['navigation'],
            'dashboardModule' => $module,
            'additionalStyles' => ['css/finea-ui.css']
        ];
    }

    public function createClient(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::CRM_CLIENTS_MANAGE);
        
        $this->view('crm/clients/create', $this->viewData('Nouveau Client', 'clients'));
    }

    public function storeClient(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::CRM_CLIENTS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/crm/clients/nouveau');
        }
        
        $data = [
            'type' => $_POST['type'] ?? 'client',
            'name' => $_POST['name'] ?? '',
            'contact_name' => $_POST['contact_name'] ?? null,
            'email' => $_POST['email'] ?? null,
            'phone' => $_POST['phone'] ?? null,
            'country' => $_POST['country'] ?? null,
            'city' => $_POST['city'] ?? null,
        ];
        
        if (empty($data['name'])) {
            Session::flash('error', 'Le nom du client est obligatoire.');
            $this->redirect('/crm/clients/nouveau');
        }

        $this->crmRepo->createClient($data, Auth::id());
        
        Session::flash('success', 'Le client a été créé avec succès.');
        $this->redirect('/crm/clients');
    }
}

