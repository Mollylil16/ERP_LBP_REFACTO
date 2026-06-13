<?php

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Models\Database;
use App\Middleware\GuestMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;

/**
 * Gère les actions liées à l'authentification.
 */
class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        $pdo = Database::getConnection();

        $userRepository = new UserRepository($pdo);

        $this->authService = new AuthService($userRepository);
    }

    /**
     * Affiche la page d'inscription.
     */
    public function showRegister(): void
    {
        GuestMiddleware::check();

        $this->view('auth/register', [
            'pageTitle' => 'Créer un compte',
        ]);
    }

    /**
     * Traite le formulaire d'inscription.
     */
    public function register(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Jeton CSRF invalide.');
            $this->back();
        }

        $result = $this->authService->register($_POST);

        if (!$result['success']) {
            Session::flash('error', $result['message']);
            $this->back();
        }

        Session::flash('success', $result['message']);

        $this->redirect('/login');
    }

    /**
     * Affiche la page de connexion.
     */
    public function showLogin(): void
    {
        GuestMiddleware::check();

        $this->view('auth/login', [
            'pageTitle' => 'Connexion',
        ]);
    }

    /**
     * Traite le formulaire de connexion.
     */
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

        $user = $result['user'];

        Session::set('auth_user_id', $user->id);

        Session::flash('success', 'Connexion réussie.');

        $this->redirect('/selection_portail.php');
    }

    /**
     * Déconnecte l'utilisateur courant.
     */
    public function logout(): void
    {
        Session::forget('auth_user_id');

        Session::flash('success', 'Déconnexion effectuée.');

        $this->redirect('/');
    }
}
