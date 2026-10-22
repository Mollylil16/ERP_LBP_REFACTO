<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Messagerie\MessagerieRepository;
use App\Services\Messagerie\MessagerieEspace;
use App\View\Pages\Admin\MessagerieEspacePage;

/**
 * L'espace disque de la messagerie, réservé à l'administrateur.
 *
 * Une lecture seule : la jauge ne supprime rien. Le seuil d'alerte vit dans
 * MessagerieEspace::SEUIL_ALERTE_OCTETS (20 Go).
 */
final class AdminMessagerieController extends AdminBaseController
{
    public function espace(): void
    {
        AdminMiddleware::check();

        $etat = (new MessagerieEspace(new MessagerieRepository(Database::getConnection())))->etat();

        $this->adminView('admin/messagerie_espace', 'Espace de la messagerie', 'messagerie-espace', [
            'page' => new MessagerieEspacePage($etat),
        ]);
    }
}
