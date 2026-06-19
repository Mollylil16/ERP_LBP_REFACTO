<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Admin\PermissionRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Rh\RhPersonnelRepository;
use App\Services\Admin\AdminService;
use App\View\Pages\Admin\PermissionEditPage;
use App\View\Pages\Admin\PermissionMatrixPage;
use RuntimeException;

final class AdminPermissionController extends AdminBaseController
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
        $data = $this->service->matrix();
        $this->adminView('admin/permissions/matrix', 'Matrice des permissions', 'permissions', [
            'page' => new PermissionMatrixPage($data['entities'], $data['users']),
        ]);
    }

    public function edit(string $id): void
    {
        AdminMiddleware::check();
        try {
            $data = $this->service->user((int) $id);
            $this->adminView('admin/permissions/edit', 'Droits utilisateur', 'permissions', [
                'page' => new PermissionEditPage($data['user'], $data['permissions']),
            ]);
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
}
