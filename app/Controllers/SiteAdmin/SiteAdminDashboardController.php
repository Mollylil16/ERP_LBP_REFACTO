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
use App\View\Pages\SiteAdmin\AnalyticsPage;
use App\Repositories\Site\WebsiteAnalyticsRepository;
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
                $data['announcements'],
                $data['articles'],
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
            $this->website->saveSlide($_POST, $_FILES['slide_image'] ?? null);
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

    public function saveAnnouncement(): void
    {
        AuthMiddleware::check();
        $this->verifyCsrf();
        try {
            $this->website->saveAnnouncement($_POST);
            Session::flash('success', 'L’annonce a été enregistrée.');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        $this->redirect('/site-admin/configuration#announcements');
    }

    public function saveArticle(): void
    {
        AuthMiddleware::check();
        $this->verifyCsrf();
        try {
            $this->website->saveArticle($_POST);
            Session::flash('success', 'L’article a été enregistré.');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        $this->redirect('/site-admin/configuration#articles');
    }

    public function analytics(): void
    {
        AuthMiddleware::check();
        $data = (new WebsiteAnalyticsRepository(Database::getConnection()))->dashboard();
        $this->siteAdminView('site_admin/analytics', 'Statistiques du site', 'analytics', [
            'page' => new AnalyticsPage($data),
        ], [
            'accent' => '#14b8a6', 'accent2' => '#0f766e',
            'gradient' => 'linear-gradient(135deg,#0f766e,#14b8a6)', 'iconKey' => 'website',
        ], ['additionalStyles' => ['css/finea-ui.css', 'css/site-admin.css']]);
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'La session du formulaire a expiré.');
            $this->redirect('/site-admin/configuration');
        }
    }
}
