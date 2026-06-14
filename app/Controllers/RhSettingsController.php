<?php

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\RhSettingsRepository;
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
        $this->view('rh/settings/index', [
            'pageTitle' => 'Parametrage RH',
            'moduleName' => 'Ressources Humaines',
            'moduleCode' => 'RH',
            'activeModule' => 'settings',
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css'],
            'additionalScripts' => ['js/rh.js'],
            'moduleNavigation' => $moduleNavigation,
            'catalogs' => $this->repository->catalogs(),
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/parametrage');
        }
        try {
            $this->repository->save((string)($_POST['catalog'] ?? ''), $_POST);
            Session::flash('success', 'Parametre RH enregistre.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/rh/parametrage');
    }

    public function toggle(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/parametrage');
        }
        try {
            $this->repository->toggle((string)($_POST['catalog'] ?? ''), (int)($_POST['id'] ?? 0));
            Session::flash('success', 'Statut du parametre mis a jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/rh/parametrage');
    }
}
