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
            $this->jetonPerime();

            return;
        }

        $result = $this->authService->login($_POST);
        if (!$result['success']) {
            Session::flash('error', $result['message']);
            $this->back();
        }

        \App\Security\DureeSession::ouvrir();
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

        // Mot de passe provisoire : l'agent choisit le sien avant d'aller plus loin.
        if (self::doitChangerMotDePasse($utilisateurId)) {
            \App\Security\MotDePasseProvisoire::exiger(true);
            Session::flash('success', 'Bienvenue. Choisissez maintenant votre propre mot de passe.');
            $this->redirect('/compte/mot-de-passe');
        }

        Session::flash('success', 'Connexion réussie.');
        $this->redirect('/selection_portail');
    }

    private static function doitChangerMotDePasse(int $utilisateurId): bool
    {
        try {
            $stmt = \App\Models\Database::getConnection()
                ->prepare('SELECT doit_changer_mdp FROM users WHERE id = :id');
            $stmt->execute(['id' => $utilisateurId]);

            return (int) $stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            // Colonne pas encore créée : rien n'est exigé.
            return false;
        }
    }

    /**
     * Le jeton de la page de connexion n'est plus reconnu.
     *
     * Le logiciel repondait « Jeton CSRF invalide » puis renvoyait l'agent sur
     * la page precedente. Deux defauts : la phrase ne veut rien dire pour lui,
     * et le retour dependait du referent envoye par le navigateur — quand il
     * manque, l'agent retombait sur la meme page morte et tournait en rond.
     * C'est ce qui a tenu KOUAKOU SALES dehors du 09 au 13/10/2026 alors que
     * son compte etait actif et son mot de passe bon.
     *
     * On reaffiche donc le formulaire NOUS-MEMES, avec un jeton frais et une
     * phrase qui dit quoi faire. Rien n'est affaibli : personne n'est connecte,
     * le mot de passe est redemande.
     */
    private function jetonPerime(): void
    {
        // Le navigateur n'a envoye aucun cookie de session : ce n'est pas une
        // page trop vieille, c'est le navigateur qui refuse de les garder. Les
        // deux causes n'appellent pas le meme geste, on ne les confond pas.
        $cookieAbsent = empty($_COOKIE[session_name()]);

        $message = $cookieAbsent
            ? "Votre navigateur n'a pas accepté le cookie de connexion. Autorisez les cookies "
                . "pour ce site, ou essayez une fenêtre de navigation privée, puis reconnectez-vous."
            : "Cette page de connexion était ouverte depuis trop longtemps et n'était plus valide. "
                . "Elle vient d'être rafraîchie : saisissez à nouveau votre mot de passe.";

        Session::flash('error', $message);

        // La vue regenere le jeton depuis la session courante : la page rendue
        // ici est donc utilisable immediatement, sans que l'agent ait a la
        // recharger lui-meme.
        $this->view('auth/login', ['pageTitle' => 'Connexion']);
    }

    public function logout(): void
    {
        Session::forget('auth_user_id');
        Session::flash('success', 'Déconnexion effectuée.');
        $this->redirect('/');
    }
}
