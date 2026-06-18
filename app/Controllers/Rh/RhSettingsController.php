<?php

namespace App\Controllers\Rh;

use App\Controllers\BaseController;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhSettingsRepository;
use RuntimeException;

class RhSettingsController extends BaseController
{
    private RhSettingsRepository $repository;

    public function __construct()
    {
        $this->repository = new RhSettingsRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();
        require BASE_PATH . '/views/rh/_navigation.php';
        $catalogs = $this->repository->catalogs();
        $requestedCatalog = (string) ($_GET['catalog'] ?? '');
        $activeCatalog = isset($catalogs[$requestedCatalog])
            ? $requestedCatalog
            : (string) array_key_first($catalogs);

        $this->view('rh/settings/index', [
            'pageTitle' => 'Parametrage RH',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'settings',
            'additionalStyles' => [
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                'css/finea-ui.css',
                'css/rh.css',
            ],
            'additionalScripts' => [
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                'js/rh.js',
            ],
            'moduleNavigation' => $moduleNavigation,
            'catalogs' => $catalogs,
            'activeCatalog' => $activeCatalog,
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();
        $catalog = (string) ($_POST['catalog'] ?? '');
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirectToCatalog($catalog);
        }
        try {
            $this->repository->save($catalog, $_POST);
            Session::flash('success', 'Parametre RH enregistre.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirectToCatalog($catalog);
    }

    public function toggle(): void
    {
        AuthMiddleware::check();
        $catalog = (string) ($_POST['catalog'] ?? '');
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirectToCatalog($catalog);
        }
        try {
            $this->repository->toggle($catalog, (int)($_POST['id'] ?? 0));
            Session::flash('success', 'Statut du parametre mis a jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirectToCatalog($catalog);
    }

    private function redirectToCatalog(string $catalog): void
    {
        $path = '/rh/parametrage';
        if ($catalog !== '') {
            $path .= '?catalog=' . rawurlencode($catalog);
        }
        $this->redirect($path);
    }
}
