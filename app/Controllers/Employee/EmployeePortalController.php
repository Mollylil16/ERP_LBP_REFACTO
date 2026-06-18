<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Controllers\BaseController;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Employee\EmployeeDashboardRepository;
use App\Repositories\Employee\EmployeePortalRepository;
use App\Services\Employee\EmployeeDashboardService;
use App\Services\Employee\EmployeePortalService;
use RuntimeException;

class EmployeePortalController extends BaseController
{
    private EmployeePortalService $service;
    private EmployeeDashboardService $dashboardService;

    public function __construct()
    {
        $portalRepository = new EmployeePortalRepository(Database::getConnection());
        $this->service = new EmployeePortalService($portalRepository);
        $this->dashboardService = new EmployeeDashboardService(new EmployeeDashboardRepository($portalRepository));
    }

    public function index(): void
    {
        AuthMiddleware::check();
        try {
            $this->view('employee/dashboard', $this->viewData('Mon espace employé', 'dashboard') + $this->dashboardService->dashboard(Auth::user()));
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/selection_portail');
        }
    }

    public function createRequest(): void
    {
        AuthMiddleware::check();
        $this->view('employee/request-form', $this->viewData('Nouvelle demande RH', 'requests') + ['csrfToken' => Csrf::token()]);
    }

    public function storeRequest(): void
    {
        $this->guard();
        try {
            $id = $this->service->createRequest(Auth::user(), $_POST, $_FILES);
            Session::flash('success', 'Votre demande a été soumise et transmise au manager.');
            $this->redirect('/espace-employe/demandes/' . $id);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back();
        }
    }

    public function showRequest(string $id): void
    {
        AuthMiddleware::check();
        try {
            $this->view('employee/request-show', $this->viewData('Suivi de ma demande', 'requests') + [
                'request' => $this->service->request(Auth::user(), (int) $id),
                'csrfToken' => Csrf::token(),
            ]);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/espace-employe');
        }
    }

    public function cancelRequest(string $id): void
    {
        $this->guard();
        try {
            $this->service->cancelRequest(Auth::user(), (int) $id);
            Session::flash('success', 'La demande a été annulée.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/espace-employe/demandes/' . (int) $id);
    }

    public function respondExplanation(string $id): void
    {
        $this->guard();
        try {
            $this->service->respondExplanation(Auth::user(), (int) $id, $_POST);
            Session::flash('success', 'Votre réponse a été transmise aux RH.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/espace-employe');
    }

    private function guard(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Jeton CSRF invalide.');
            $this->back();
        }
    }

    private function viewData(string $title, string $active): array
    {
        return [
            'pageTitle' => $title,
            'moduleName' => 'Espace employé',
            'moduleCode' => 'EMP',
            'activeModule' => $active,
            'moduleTheme' => ['accent' => '#0ea5e9', 'accent2' => '#0369a1', 'gradient' => 'linear-gradient(135deg,#0369a1,#0ea5e9)', 'iconKey' => 'employee'],
            'moduleNavigation' => [
                ['group' => 'Accueil', 'key' => 'dashboard', 'label' => 'Mon tableau de bord', 'icon' => 'DB', 'url' => 'espace-employe', 'available' => true],
                ['group' => 'Mes démarches', 'key' => 'requests', 'label' => 'Mes demandes RH', 'icon' => 'DR', 'url' => 'espace-employe/demandes/nouvelle', 'available' => true],
                ['group' => 'Temps & échanges', 'key' => 'attendance', 'label' => 'Mon pointage', 'icon' => 'PT', 'url' => 'espace-employe#pointage', 'available' => true],
                ['group' => 'Temps & échanges', 'key' => 'explanations', 'label' => 'Mes explications', 'icon' => 'EX', 'url' => 'espace-employe#explications', 'available' => true],
                ['group' => 'Mon dossier', 'key' => 'documents', 'label' => 'Mes documents', 'icon' => 'DO', 'url' => 'espace-employe#documents', 'available' => true],
            ],
            'additionalStyles' => ['css/finea-ui.css', 'css/employee.css'],
            'additionalScripts' => ['js/employee.js'],
        ];
    }
}
