<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Admin\AdminDashboardRepository;
use App\Repositories\Admin\PermissionRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\Admin\AdminDashboardService;
use App\View\Pages\Admin\DashboardPage;

final class AdminDashboardController extends AdminBaseController
{
    private AdminDashboardService $service;

    /** Ce qui demande une decision : les comptes qui clochent. */
    private \App\Repositories\Admin\RolesRepository $roles;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->service = new AdminDashboardService(new AdminDashboardRepository(
            new UserRepository($pdo),
            new PermissionRepository($pdo),
        ));
        $this->roles = new \App\Repositories\Admin\RolesRepository($pdo);
    }

    public function index(): void
    {
        AdminMiddleware::check();
        /*
         * Les cinq compteurs d avant denombraient sans rien demander : savoir
         * qu il y a quarante comptes n a jamais fait agir personne. Trois
         * d entre eux cedent la place a ce qui attend une decision.
         */
        $attente = [
            'sans_role' => $this->roles->comptesSansRole(),
            'dormants' => $this->roles->comptesDormants(),
            'administrateurs' => $this->roles->administrateurs(),
            'du_mois' => $this->roles->comptesDuMois(),
        ];

        $this->adminView('admin/dashboard', 'Tableau de bord', 'dashboard', [
            'page' => new DashboardPage($this->service->dashboard() + ['attente' => $attente]),
        ]);
    }
}
