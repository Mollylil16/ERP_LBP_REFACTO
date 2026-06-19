<?php

declare(strict_types=1);

namespace App\Controllers\SiteAdmin;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Site\WebsiteRepository;
use App\Repositories\SiteAdmin\SiteAdminDashboardRepository;
use App\Services\Site\WebsiteService;
use App\Services\SiteAdmin\SiteAdminDashboardService;
use App\View\Pages\SiteAdmin\ConfigurationPage;
use App\View\Pages\SiteAdmin\DashboardPage;
use RuntimeException;

final class SiteAdminDashboardController extends SiteAdminBaseController
{
    private SiteAdminDashboardService $service;
    private WebsiteService $website;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->service = new SiteAdminDashboardService(new SiteAdminDashboardRepository($pdo));
        $this->website = new WebsiteService(new WebsiteRepository($pdo));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $module = $this->service->dashboard();

        $this->siteAdminView(
            'site_admin/dashboard',
            'Tableau de bord ' . (string) $module['label'],
            'dashboard',
            ['page' => new DashboardPage($module)],
            $module,
        );
    }

    public function configuration(): void
    {
        AuthMiddleware::check();
        $data = $this->website->administration();

        $this->siteAdminView('site_admin/configuration', 'Personnalisation du site', 'configuration', [
            'page' => new ConfigurationPage(
                Csrf::token(),
                $data['branding'],
                $data['slides'],
                $data['products'],
            ),
        ], [
            'accent' => '#14b8a6',
            'accent2' => '#0f766e',
            'gradient' => 'linear-gradient(135deg,#0f766e,#14b8a6)',
            'iconKey' => 'website',
        ], [
            'additionalStyles' => ['css/finea-ui.css', 'css/site-admin.css'],
            'additionalScripts' => ['js/site-admin.js'],
        ]);
    }

    public function updateBranding(): void
    {
        AuthMiddleware::check();
        $this->verifyCsrf();
        try {
            $this->website->updateBranding($_POST);
            Session::flash('success', 'L’identité visuelle du site a été mise à jour.');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        $this->redirect('/site-admin/configuration');
    }

    public function saveSlide(): void
    {
        AuthMiddleware::check();
        $this->verifyCsrf();
        try {
            $this->website->saveSlide($_POST);
            Session::flash('success', 'Le carrousel public a été mis à jour.');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        $this->redirect('/site-admin/configuration#carousel');
    }

    public function saveProduct(): void
    {
        AuthMiddleware::check();
        $this->verifyCsrf();
        try {
            $this->website->saveProduct($_POST);
            Session::flash('success', 'L’offre marketplace a été enregistrée.');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        $this->redirect('/site-admin/configuration#marketplace');
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'La session du formulaire a expiré.');
            $this->redirect('/site-admin/configuration');
        }
    }
}
