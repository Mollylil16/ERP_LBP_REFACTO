<?php

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\PermissionRepository;
use App\Repositories\RhPersonnelRepository;
use App\Repositories\UserRepository;
use App\Services\AdminService;
use RuntimeException;

class AdminPermissionController extends BaseController
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

    public function matrix(): void
    {
        AdminMiddleware::check();
        $this->view('admin/permissions/matrix', $this->viewData('Matrice des permissions', 'permissions') + $this->service->matrix());
    }

    public function edit(string $id): void
    {
        AdminMiddleware::check();
        try {
            $this->view('admin/permissions/edit', $this->viewData('Droits utilisateur', 'permissions') + $this->service->user((int) $id));
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/admin/permissions');
        }
    }

    public function update(string $id): void
    {
        AdminMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Jeton CSRF invalide.');
            $this->back();
        }

        try {
            $this->service->savePermissions((int) $id, $_POST);
            Session::flash('success', 'Les permissions ont été enregistrées.');
            $this->redirect('/admin/users/' . (int) $id);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
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
