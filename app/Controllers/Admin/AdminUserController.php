<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Admin\PermissionRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Rh\RhPersonnelRepository;
use App\Services\Admin\AdminService;
use App\View\Pages\Admin\UserFormPage;
use App\View\Pages\Admin\UserIndexPage;
use App\View\Pages\Admin\UserShowPage;
use RuntimeException;

final class AdminUserController extends AdminBaseController
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
        $this->adminView('admin/users/index', 'Utilisateurs', 'users', [
            'page' => new UserIndexPage($this->service->listUsers($_GET)),
        ]);
    }

    public function create(): void
    {
        AdminMiddleware::check();
        $data = $this->service->userCreationData();
        $this->adminView('admin/users/form', 'Nouvel utilisateur', 'users', [
            'page' => new UserFormPage(
                'Nouvel utilisateur',
                null,
                null,
                $data['employees'],
                $data['permissions'],
                '/admin/users',
                'Créer l’utilisateur',
            ),
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
            $data = $this->service->user((int) $id);
            $this->adminView('admin/users/show', 'Profil utilisateur', 'users', [
                'page' => new UserShowPage(
                    $data['user'],
                    $data['employee'],
                    $data['permissions'],
                    (int) Auth::id(),
                ),
            ]);
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
            $this->adminView('admin/users/form', 'Modifier l’utilisateur', 'users', [
                'page' => new UserFormPage(
                    'Modifier l’utilisateur',
                    $data['user'],
                    $data['employee'],
                    [],
                    $data['permissions'],
                    '/admin/users/' . (int) $id . '/modifier',
                    'Enregistrer les modifications',
                ),
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
}
