<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Middleware\AuthMiddleware;

/**
 * Le guide interactif du module RH.
 *
 * Aucun controle de role ici, et c'est voulu : l'adresse commence par /rh,
 * donc ModuleReserve a deja refuse tout le monde sauf le DG, la responsable RH
 * et l'administrateur, avant meme d'entrer dans ce controleur. En rajouter un
 * second ne fermerait rien de plus et ferait croire, a la lecture, que la
 * regle se tient a deux endroits.
 */
final class RhGuideController extends RhBaseController
{
    public function index(): void
    {
        AuthMiddleware::check();

        $this->rhView('rh/guide', 'Guide interactif — Ressources humaines', 'guide');
    }
}
