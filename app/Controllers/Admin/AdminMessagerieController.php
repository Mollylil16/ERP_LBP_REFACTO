<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Messagerie\MessagerieRepository;
use App\Services\Messagerie\GroupesAutomatiques;
use App\Services\Messagerie\MessagerieEspace;
use App\Services\Shared\AuditLogService;
use App\View\Pages\Admin\MessagerieEspacePage;
use Throwable;

/**
 * L'espace disque de la messagerie et ses groupes automatiques, réservés à
 * l'administrateur.
 *
 * La jauge est une lecture seule : elle ne supprime rien. Le seuil d'alerte
 * vit dans MessagerieEspace::SEUIL_ALERTE_OCTETS (20 Go). Le bouton
 * « Resynchroniser » lance la même passe complète que la commande de nuit
 * (GroupesAutomatiques::synchroniserTout), sans attendre la nuit.
 */
final class AdminMessagerieController extends AdminBaseController
{
    public function espace(): void
    {
        AdminMiddleware::check();

        $pdo = Database::getConnection();
        $etat = (new MessagerieEspace(new MessagerieRepository($pdo)))->etat();

        $groupes = [];
        try {
            $groupes = GroupesAutomatiques::parDefaut($pdo)->etat();
        } catch (Throwable $e) {
            error_log('[Messagerie groupes] état illisible : ' . $e->getMessage());
        }

        $this->adminView('admin/messagerie_espace', 'Espace de la messagerie', 'messagerie-espace', [
            'page' => new MessagerieEspacePage($etat, $groupes),
        ]);
    }

    public function resynchroniserGroupes(): void
    {
        AdminMiddleware::check();
        if (!Csrf::verify(is_string($_POST['_csrf_token'] ?? null) ? $_POST['_csrf_token'] : null)) {
            Session::flash('error', 'La page a expiré. Rechargez-la puis recommencez.');
            $this->redirect('/admin/messagerie/espace');

            return;
        }

        try {
            $bilan = GroupesAutomatiques::parDefaut()->synchroniserTout();
            AuditLogService::log('MESSAGERIE_GROUPES_RESYNCHRONISES', 'msg_conversation', 0, null, $bilan);

            $resume = $bilan['groupes_crees'] . ' groupe(s) créé(s), '
                . $bilan['membres_ajoutes'] . ' membre(s) ajouté(s), '
                . $bilan['membres_retires'] . ' retiré(s), '
                . $bilan['roles_changes'] . ' rôle(s) changé(s).';

            if ($bilan['erreurs'] > 0) {
                Session::flash('error', 'Synchronisation incomplète : ' . $bilan['erreurs'] . ' opération(s) en échec (voir le journal PHP). ' . $resume);
            } else {
                Session::flash('success', 'Groupes automatiques à jour : ' . $resume);
            }
        } catch (Throwable $e) {
            error_log('[Messagerie groupes] resynchronisation : ' . $e->getMessage());
            Session::flash('error', 'La synchronisation n’a pas pu aboutir. Réessayez dans un instant.');
        }

        $this->redirect('/admin/messagerie/espace');
    }
}
