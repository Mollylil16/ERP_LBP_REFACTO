<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Repositories\CrmRepository;
use App\Security\PermissionEntityRegistry;
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
        AuthMiddleware::checkPermission(PermissionEntityRegistry::CRM_CLIENTS, 'view');
        
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->crmDashboard();
        $this->view('modules/dashboard', ['pageTitle'=>'Tableau de bord CRM','moduleName'=>'CRM','moduleCode'=>'CRM','moduleTheme'=>$module,'activeModule'=>'dashboard','moduleNavigation'=>$module['navigation'],'dashboardModule'=>$module,'additionalStyles'=>['css/finea-ui.css']]);
    }

    public function clients(): void
    {
        AuthMiddleware::check();
        AuthMiddleware::checkPermission(PermissionEntityRegistry::CRM_CLIENTS, 'view');
        
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
        AuthMiddleware::checkPermission(PermissionEntityRegistry::CRM_CLIENTS, 'manage');
        
        $this->view('crm/clients/create', $this->viewData('Nouveau Client', 'clients'));
    }

    public function storeClient(): void
    {
        AuthMiddleware::check();
        AuthMiddleware::checkPermission(PermissionEntityRegistry::CRM_CLIENTS, 'manage');
        
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
            $_SESSION['error'] = 'Le nom du client est obligatoire.';
            header('Location: /crm/clients/nouveau');
            exit;
        }

        $this->crmRepo->createClient($data, Auth::id());
        
        $_SESSION['success'] = 'Le client a été créé avec succès.';
        header('Location: /crm/clients');
        exit;
    }
}
