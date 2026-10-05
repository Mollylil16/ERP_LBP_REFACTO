<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\GuestMiddleware;
use App\Models\Database;
use App\Repositories\Admin\UserRepository;
use App\Services\Auth\AuthService;

class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService(
            new UserRepository(Database::getConnection())
        );
    }

    public function showLogin(): void
    {
        GuestMiddleware::check();
        $this->view('auth/login', ['pageTitle' => 'Connexion']);
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Jeton CSRF invalide.');
            $this->back();
        }

        $result = $this->authService->login($_POST);
        if (!$result['success']) {
            Session::flash('error', $result['message']);
            $this->back();
        }

        Session::set('auth_user_id', $result['user']->id);

        /*
         * La derniere connexion, et la trace du passage.
         *
         * Sans cette date, impossible de dire quels comptes dorment depuis six
         * mois — ni de voir un compte inactif se reveiller. Ni l une ni l autre
         * ecriture ne doit empecher d entrer : un journal qui bloque la
         * connexion finirait desactive.
         */
        $utilisateurId = (int) $result['user']->id;

        try {
            $stmt = \App\Models\Database::getConnection()
                ->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $utilisateurId]);
        } catch (\Throwable $e) {
            error_log('[AuthController] last_login_at : ' . $e->getMessage());
        }

        \App\Services\Shared\AuditLogService::log('login', 'users', $utilisateurId, null, null);

        Session::flash('success', 'Connexion réussie.');
        $this->redirect('/selection_portail');
    }

    public function logout(): void
    {
        Session::forget('auth_user_id');
        Session::flash('success', 'Déconnexion effectuée.');
        $this->redirect('/');
    }
}
