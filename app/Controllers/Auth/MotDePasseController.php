<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Admin\UserRepository;
use App\Security\MotDePasseProvisoire;
use App\Services\Shared\AuditLogService;

/**
 * L'agent choisit son propre mot de passe : obligatoirement après une
 * connexion avec un mot de passe provisoire, librement ensuite.
 */
final class MotDePasseController extends BaseController
{
    public function show(): void
    {
        AuthMiddleware::check();
        $user = Auth::user();

        $this->view('auth/mot_de_passe', [
            'pageTitle' => 'Votre mot de passe',
            'nomComplet' => (string) ($user?->fullName ?? ''),
            'obligatoire' => MotDePasseProvisoire::estExige(),
        ]);
    }

    public function update(): void
    {
        AuthMiddleware::check();
        $user = Auth::user();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'La page était ouverte depuis trop longtemps. Recommencez.');
            $this->redirect('/compte/mot-de-passe');
        }

        $actuel = (string) ($_POST['actuel'] ?? '');
        $nouveau = (string) ($_POST['nouveau'] ?? '');
        $confirmation = (string) ($_POST['confirmation'] ?? '');

        if ($user === null || !password_verify($actuel, $user->passwordHash)) {
            Session::flash('error', 'L’ancien mot de passe saisi est incorrect.');
            $this->redirect('/compte/mot-de-passe');
        }

        $refus = MotDePasseProvisoire::refus($nouveau, $confirmation, $actuel, (string) $user->email);
        if ($refus !== null) {
            Session::flash('error', $refus);
            $this->redirect('/compte/mot-de-passe');
        }

        $depot = new UserRepository(Database::getConnection());
        $depot->updatePassword((int) $user->id, password_hash($nouveau, PASSWORD_DEFAULT));
        $depot->exigerChangementMotDePasse((int) $user->id, false);
        MotDePasseProvisoire::exiger(false);

        // Nouveau secret, nouvel identifiant de session.
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        // Le geste est journalisé, jamais le secret.
        AuditLogService::log('password_change', 'users', (int) $user->id, null, ['par' => 'utilisateur']);

        Session::flash('success', 'Votre mot de passe est enregistré. Vous seul le connaissez désormais.');
        $this->redirect('/selection_portail');
    }
}
