<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\PermissionRepository;
use App\Repositories\RhPersonnelRepository;
use App\Repositories\UserRepository;
use App\Services\AdminService;
use RuntimeException;

class AdminUserController extends BaseController
{
    private AdminService $service;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->service = new AdminService(
            new UserRepository($pdo),
            new PermissionRepository($pdo),
            new RhPersonnelRepository($pdo),
            $pdo
        );
    }

    public function index(): void
    {
        AdminMiddleware::check();
        $this->view('admin/users/index', $this->viewData('Utilisateurs', 'users') + $this->service->listUsers($_GET));
    }

    public function create(): void
    {
        AdminMiddleware::check();
        $this->view('admin/users/form', $this->viewData('Nouvel utilisateur', 'users') + $this->service->userCreationData() + [
            'user' => null,
            'employee' => null,
            'formAction' => '/admin/users',
            'submitLabel' => 'Créer l’utilisateur',
        ]);
    }

    public function store(): void
    {
        $this->guardWrite();
        try {
            $id = $this->service->createUser($_POST);
            Session::flash('success', 'Le compte utilisateur a été créé.');
            $this->redirect('/admin/users/' . $id);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back();
        }
    }

    public function show(string $id): void
    {
        AdminMiddleware::check();
        try {
            $this->view('admin/users/show', $this->viewData('Profil utilisateur', 'users') + $this->service->user((int) $id));
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/admin/users');
        }
    }

    public function edit(string $id): void
    {
        AdminMiddleware::check();
        try {
            $data = $this->service->user((int) $id);
            $this->view('admin/users/form', $this->viewData('Modifier l’utilisateur', 'users') + [
                'user' => $data['user'],
                'employee' => $data['employee'],
                'employees' => [],
                'permissions' => $data['permissions'],
                'formAction' => '/admin/users/' . (int) $id . '/modifier',
                'submitLabel' => 'Enregistrer les modifications',
            ]);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/admin/users');
        }
    }

    public function update(string $id): void
    {
        $this->guardWrite();
        try {
            $this->service->updateUser((int) $id, $_POST, (int) Auth::id());
            Session::flash('success', 'Le compte utilisateur a été mis à jour.');
            $this->redirect('/admin/users/' . (int) $id);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back();
        }
    }

    public function deactivate(string $id): void
    {
        $this->guardWrite();
        try {
            $this->service->setUserActive((int) $id, false, (int) Auth::id());
            Session::flash('success', 'Le compte a été désactivé. Ses accès sont désormais coupés.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/users/' . (int) $id);
    }

    public function activate(string $id): void
    {
        $this->guardWrite();
        try {
            $this->service->setUserActive((int) $id, true, (int) Auth::id());
            Session::flash('success', 'Le compte utilisateur a été réactivé.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/users/' . (int) $id);
    }

    private function guardWrite(): void
    {
        AdminMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Jeton CSRF invalide.');
            $this->back();
        }
    }

    private function viewData(string $pageTitle, string $activeModule): array
    {
        return [
            'pageTitle' => $pageTitle,
            'moduleName' => 'Administration',
            'moduleCode' => 'ADM',
            'activeModule' => $activeModule,
            'additionalStyles' => ['css/finea-ui.css', 'css/admin.css'],
            'additionalScripts' => ['js/admin.js'],
        ];
    }
}
