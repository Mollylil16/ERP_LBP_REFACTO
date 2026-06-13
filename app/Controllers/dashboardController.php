<?php

namespace App\Controllers;

use App\Models\Database;
use App\Helpers\Auth;
use App\Middleware\AuthMiddleware;
use PDO;

class DashboardController extends BaseController
{

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function index(): void
    {
        AuthMiddleware::check();


        /** Récupérer les données nécessaires pour le tableau de bord.
         * Par exemple, les sujets suivis par l'utilisateur et leurs derniers événements d'activité.
         */
        $user = [
            'id' => Auth::id(),
            'name' => Auth::user()?->fullName ?? 'Administrateur',
        ];

        $this->view('dashboard/index', [
            'pageTitle' => 'Tableau de bord',
            'user' => $user
        ]);
    }
}
