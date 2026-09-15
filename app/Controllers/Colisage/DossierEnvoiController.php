<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Security\DossierEnvoiAcces;
use App\Services\Colisage\DossierEnvoiInvalide;
use App\Services\Colisage\DossierEnvoiService;
use Throwable;

/**
 * Préparer un départ et suivre ces départs, dans Facturation > Activité.
 *
 * L'agent export saisit le document de la compagnie ; le Directeur général le
 * compare à la saisie des colis et valide. Tout autre compte est renvoyé avec
 * un message d'habilitation.
 */
final class DossierEnvoiController extends ColisageBaseController
{
    private DossierEnvoiService $service;

    public function __construct()
    {
        $this->service = DossierEnvoiService::creer();
    }

    // ------------------------------------------------------------------
    // Préparer un départ
    // ------------------------------------------------------------------

    public function preparer(): void
    {
        $acces = $this->acces();

        $this->afficherPreparation($acces, $this->service->nouveauDepart(), []);
    }

    public function enregistrerDepart(): void
    {
        $acces = $this->acces();

        // Au-delà de post_max_size, PHP vide $_POST et $_FILES sans prévenir.
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Session::flash('error', "Le document joint est trop lourd pour être transmis (10 Mo au maximum). Aucun départ n'a été enregistré.");
            $this->rediriger('colisage/departs');
        }

        $this->exigerJeton('colisage/departs');

        try {
            $depart = $this->service->creerDepart($_POST, $acces);
        } catch (DossierEnvoiInvalide $e) {
            $this->afficherPreparation($acces, $this->service->brouillonDepuisSaisie($_POST, null), $e->erreurs);

            return;
        } catch (Throwable $e) {
            error_log('[Dossiers envoi] creerDepart : ' . $e->getMessage());
            $this->afficherPreparation(
                $acces,
                $this->service->brouillonDepuisSaisie($_POST, null),
                ["Le départ n'a pas pu être enregistré. Aucun colis n'a été modifié : réessayez."]
            );

            return;
        }

        $message = 'Départ ' . $depart['numero'] . ' enregistré.';
        $fichier = $_FILES['document_compagnie'] ?? null;

        if (is_array($fichier) && (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $this->service->deposerDocument($depart['id'], ['type_document' => 'PIECE_TRANSPORT'], $fichier, $acces);
                $message .= ' Document de la compagnie joint.';
            } catch (DossierEnvoiInvalide $e) {
                Session::flash('error', "Le document de la compagnie n'a pas été joint : " . implode(' ', $e->erreurs) . ' Joignez-le dans les pièces du départ.');
            } catch (Throwable $e) {
                error_log('[Dossiers envoi] document à la création : ' . $e->getMessage());
                Session::flash('error', "Le document de la compagnie n'a pas pu être joint. Joignez-le dans les pièces du départ.");
            }
        }

        Session::flash('success', $message);
        $this->rediriger('colisage/envois/' . $depart['id']);
    }

    // ------------------------------------------------------------------
    // Fiche d'un départ
    // ------------------------------------------------------------------

    public function fiche(string $id): void
    {
        $acces = $this->acces();

        $this->afficherFiche((int) $id, $acces, null, []);
    }

    public function mettreAJour(string $id): void
    {
        $acces = $this->acces();
        $dossierId = (int) $id;
        $this->exigerJeton('colisage/envois/' . $dossierId);

        try {
            Session::flash('success', $this->service->mettreAJour($dossierId, $_POST, $acces));
            $this->rediriger('colisage/envois/' . $dossierId);
        } catch (DossierEnvoiInvalide $e) {
            $this->afficherFiche($dossierId, $acces, $this->service->brouillonDepuisSaisie($_POST, $dossierId), $e->erreurs);
        } catch (Throwable $e) {
            error_log('[Dossiers envoi] mettreAJour : ' . $e->getMessage());
            $this->afficherFiche(
                $dossierId,
                $acces,
                $this->service->brouillonDepuisSaisie($_POST, $dossierId),
                ["Le départ n'a pas pu être enregistré. Rien n'a été modifié : réessayez."]
            );
        }
    }

    public function fichePdf(string $id): void
    {
        $acces = $this->acces();
        $envois = $this->service->fiche((int) $id, $acces);

        if ($envois === null) {
            Session::flash('error', 'Départ introuvable, ou hors de votre périmètre.');
            $this->rediriger('colisage/departs');
        }

        $envois['edite_par'] = $this->nomUtilisateur();

        require BASE_PATH . '/views/colisage/envois/fiche_pdf.php';
    }

    public function deposerDocument(string $id): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;

        $fichier = is_array($_FILES['fichier'] ?? null) ? $_FILES['fichier'] : [];
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Session::flash('error', 'Le fichier est trop lourd pour être transmis : 10 Mo au maximum.');
            $this->rediriger($retour);
        }

        $this->exigerJeton($retour);
        $this->agir($retour, fn (): string => $this->service->deposerDocument((int) $id, $_POST, $fichier, $acces), 'deposerDocument');
    }

    public function telechargerDocument(string $id, string $document): void
    {
        $acces = $this->acces();
        $piece = $this->service->telechargement((int) $id, (int) $document, $acces);

        if ($piece === null) {
            Session::flash('error', 'Pièce introuvable, ou hors de votre périmètre.');
            $this->rediriger('colisage/envois/' . (int) $id);
        }

        $enLigne = in_array($piece['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);

        header('Content-Type: ' . $piece['mime']);
        header('Content-Length: ' . (string) filesize($piece['chemin']));
        header('Content-Disposition: ' . ($enLigne ? 'inline' : 'attachment') . '; filename="' . $piece['nom'] . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0');

        readfile($piece['chemin']);
        exit;
    }

    public function retirerDocument(string $id, string $document): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;
        $this->exigerJeton($retour);

        $this->agir($retour, fn (): string => $this->service->retirerDocument((int) $id, (int) $document, $acces), 'retirerDocument');
    }

    public function soumettre(string $id): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;
        $this->exigerJeton($retour);

        try {
            Session::flash('success', $this->service->soumettre((int) $id, $acces));
        } catch (DossierEnvoiInvalide $e) {
            Session::flash('error', 'Soumission impossible. Il reste : ' . implode(' ', $e->erreurs));
        } catch (Throwable $e) {
            error_log('[Dossiers envoi] soumettre : ' . $e->getMessage());
            Session::flash('error', "Le départ n'a pas pu être soumis. Réessayez.");
        }

        $this->rediriger($retour);
    }

    public function valider(string $id): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;
        $this->exigerJeton($retour);

        $this->agir($retour, fn (): string => $this->service->valider((int) $id, $_POST['commentaire'] ?? null, $acces), 'valider');
    }

    public function renvoyer(string $id): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;
        $this->exigerJeton($retour);

        $this->agir($retour, fn (): string => $this->service->renvoyer((int) $id, $_POST['motif'] ?? null, $acces), 'renvoyer');
    }

    public function rouvrir(string $id): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;
        $this->exigerJeton($retour);

        $this->agir($retour, fn (): string => $this->service->rouvrir((int) $id, $_POST['motif'] ?? null, $acces), 'rouvrir');
    }

    // ------------------------------------------------------------------
    // Validation et historique
    // ------------------------------------------------------------------

    public function aValider(): void
    {
        $acces = $this->acces();

        if (!$acces->estValideur()) {
            Session::flash('error', 'Seul le Directeur général valide les départs.');
            $this->rediriger('colisage/departs');
        }

        $this->colisageView('colisage/envois/a_valider', 'Départs à valider', 'envois_valider', [
            'envois' => ['dossiers' => $this->service->aValider($acces)],
        ]);
    }

    public function historique(): void
    {
        $acces = $this->acces();

        $this->colisageView('colisage/envois/historique', 'Historique des envois', 'envois_historique', [
            'envois' => $this->service->historique($_GET, $acces),
        ]);
    }

    public function historiquePdf(): void
    {
        $acces = $this->acces();
        $envois = $this->service->historique($_GET, $acces) + ['edite_par' => $this->nomUtilisateur()];

        require BASE_PATH . '/views/colisage/envois/historique_pdf.php';
    }

    public function historiqueExcel(): void
    {
        $acces = $this->acces();
        $envois = $this->service->historique($_GET, $acces) + ['edite_par' => $this->nomUtilisateur()];

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="historique_envois_' . $envois['filtres']['du'] . '_' . $envois['filtres']['au'] . '.xls"');
        header('Cache-Control: max-age=0');

        echo "\xEF\xBB\xBF";
        require BASE_PATH . '/views/colisage/envois/historique_excel.php';
    }

    // ------------------------------------------------------------------
    // Prestataires
    // ------------------------------------------------------------------

    public function prestataires(): void
    {
        $this->acces();

        $this->colisageView('colisage/envois/prestataires', 'Transporteurs et prestataires', 'envois_prestataires', [
            'envois' => ['prestataires' => $this->service->prestataires()],
        ]);
    }

    public function enregistrerPrestataire(): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/prestataires';
        $this->exigerJeton($retour);

        $this->agir($retour, fn (): string => $this->service->enregistrerPrestataire($_POST, $acces), 'enregistrerPrestataire');
    }

    // ------------------------------------------------------------------

    private function acces(): DossierEnvoiAcces
    {
        AuthMiddleware::check();
        $acces = DossierEnvoiAcces::courant();

        if (!$acces->peutOuvrir()) {
            Session::flash('error', "Accès refusé : vous n'avez pas l'habilitation requise pour préparer les départs.");
            $this->rediriger('colisage/dashboard');
        }

        return $acces;
    }

    /**
     * @param array<string, mixed> $brouillon
     * @param array<int, string> $erreurs
     */
    private function afficherPreparation(DossierEnvoiAcces $acces, array $brouillon, array $erreurs): void
    {
        $this->colisageView('colisage/envois/preparer', 'Préparer un départ', 'pointage_departs', [
            'envois' => $this->service->preparation($acces) + $brouillon + [
                'erreurs' => $erreurs,
                'peut_creer' => $acces->peutCreer(),
                'voit_tout' => $acces->voitTout(),
                'est_valideur' => $acces->estValideur(),
            ],
        ]);
    }

    /**
     * @param array<string, mixed>|null $brouillon
     * @param array<int, string> $erreurs
     */
    private function afficherFiche(int $id, DossierEnvoiAcces $acces, ?array $brouillon, array $erreurs): void
    {
        $fiche = $this->service->fiche($id, $acces);

        if ($fiche === null) {
            Session::flash('error', 'Départ introuvable, ou hors de votre périmètre.');
            $this->rediriger('colisage/departs');
        }

        if ($brouillon !== null) {
            $fiche['dossier'] = array_merge($fiche['dossier'], $brouillon['dossier']);
            $fiche['frais'] = $brouillon['frais'];
            $fiche['emballages'] = $brouillon['emballages'];
        }

        $fiche['erreurs'] = $erreurs;

        $this->colisageView('colisage/envois/fiche', 'Départ ' . $fiche['dossier']['numero'], 'pointage_departs', ['envois' => $fiche]);
    }

    private function agir(string $retour, callable $action, string $nom): never
    {
        try {
            Session::flash('success', (string) $action());
        } catch (DossierEnvoiInvalide $e) {
            Session::flash('error', implode(' ', $e->erreurs));
        } catch (Throwable $e) {
            error_log('[Dossiers envoi] ' . $nom . ' : ' . $e->getMessage());
            Session::flash('error', "L'opération n'a pas pu aboutir. Rien n'a été modifié : réessayez.");
        }

        $this->rediriger($retour);
    }

    private function nomUtilisateur(): string
    {
        return (string) (Auth::user()?->fullName ?? '');
    }

    private function exigerJeton(string $retour): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide. Veuillez réessayer.');
            $this->rediriger($retour);
        }
    }

    private function rediriger(string $chemin): never
    {
        header('Location: ' . View::url($chemin));
        exit;
    }
}
