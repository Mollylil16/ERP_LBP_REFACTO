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
 * Dossiers d'envoi, dans Facturation > Activité, à côté du pointage.
 *
 * Réservés à l'agent export, qui tient ses dossiers, et au Directeur général,
 * qui les valide. Tout autre compte est renvoyé avec un message d'habilitation.
 */
final class DossierEnvoiController extends ColisageBaseController
{
    private DossierEnvoiService $service;

    public function __construct()
    {
        $this->service = DossierEnvoiService::creer();
    }

    // ------------------------------------------------------------------
    // Listes
    // ------------------------------------------------------------------

    public function liste(): void
    {
        $acces = $this->acces();

        $this->colisageView(
            'colisage/envois/liste',
            $acces->voitTout() ? "Dossiers d'envoi" : "Mes dossiers d'envoi",
            'envois_liste',
            ['envois' => $this->service->liste($_GET, $acces) + $this->droitsGeneraux($acces)]
        );
    }

    public function aValider(): void
    {
        $acces = $this->acces();

        if (!$acces->estValideur()) {
            Session::flash('error', 'Seul le Directeur général valide les dossiers d\'envoi.');
            $this->rediriger('colisage/envois');
        }

        $this->colisageView('colisage/envois/a_valider', 'Dossiers à valider', 'envois_valider', [
            'envois' => ['dossiers' => $this->service->aValider($acces)] + $this->droitsGeneraux($acces),
        ]);
    }

    public function piecesManquantes(): void
    {
        $acces = $this->acces();

        $this->colisageView('colisage/envois/pieces', 'Pièces manquantes', 'envois_pieces', [
            'envois' => ['lignes' => $this->service->piecesManquantes($acces)] + $this->droitsGeneraux($acces),
        ]);
    }

    public function historique(): void
    {
        $acces = $this->acces();

        $this->colisageView('colisage/envois/historique', 'Historique des envois', 'envois_historique', [
            'envois' => $this->service->historique($_GET, $acces) + $this->droitsGeneraux($acces),
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

        $nom = 'historique_envois_' . $envois['filtres']['du'] . '_' . $envois['filtres']['au'] . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('Cache-Control: max-age=0');

        echo "\xEF\xBB\xBF";
        require BASE_PATH . '/views/colisage/envois/historique_excel.php';
    }

    // ------------------------------------------------------------------
    // Saisie
    // ------------------------------------------------------------------

    public function nouveau(): void
    {
        $acces = $this->acces();

        if (!$acces->peutCreer()) {
            Session::flash('error', "Seul l'agent export peut ouvrir un dossier d'envoi.");
            $this->rediriger('colisage/envois');
        }

        $depart = (int) ($_GET['depart'] ?? 0);
        $donnees = $this->service->nouveau($depart > 0 ? $depart : null);

        $this->afficherFormulaire($donnees, [], null);
    }

    public function enregistrer(): void
    {
        $acces = $this->acces();
        $this->exigerJeton('colisage/envois/nouveau');

        try {
            $id = $this->service->enregistrer(null, $_POST, $acces);
            Session::flash('success', "Dossier d'envoi ouvert.");
            $this->rediriger('colisage/envois/' . $id);
        } catch (DossierEnvoiInvalide $e) {
            $this->afficherFormulaire($this->service->brouillonDepuisSaisie($_POST, null), $e->erreurs, null);
        } catch (Throwable $e) {
            error_log('[Dossiers envoi] enregistrer : ' . $e->getMessage());
            $this->afficherFormulaire(
                $this->service->brouillonDepuisSaisie($_POST, null),
                ["Le dossier n'a pas pu être enregistré. Rien n'a été écrit : réessayez."],
                null
            );
        }
    }

    public function modifier(string $id): void
    {
        $acces = $this->acces();
        $donnees = $this->service->formulaire((int) $id, $acces);

        if ($donnees === null) {
            Session::flash('error', "Ce dossier n'est plus modifiable : il a été soumis, validé, ou n'est pas le vôtre.");
            $this->rediriger('colisage/envois/' . (int) $id);
        }

        $this->afficherFormulaire($donnees, [], (int) $id);
    }

    public function mettreAJour(string $id): void
    {
        $acces = $this->acces();
        $dossierId = (int) $id;
        $this->exigerJeton('colisage/envois/' . $dossierId . '/modifier');

        try {
            $this->service->enregistrer($dossierId, $_POST, $acces);
            Session::flash('success', 'Dossier enregistré.');
            $this->rediriger('colisage/envois/' . $dossierId);
        } catch (DossierEnvoiInvalide $e) {
            $this->afficherFormulaire($this->service->brouillonDepuisSaisie($_POST, $dossierId), $e->erreurs, $dossierId);
        } catch (Throwable $e) {
            error_log('[Dossiers envoi] mettreAJour : ' . $e->getMessage());
            $this->afficherFormulaire(
                $this->service->brouillonDepuisSaisie($_POST, $dossierId),
                ["Le dossier n'a pas pu être enregistré. Rien n'a été modifié : réessayez."],
                $dossierId
            );
        }
    }

    // ------------------------------------------------------------------
    // Fiche
    // ------------------------------------------------------------------

    public function fiche(string $id): void
    {
        $acces = $this->acces();
        $fiche = $this->service->fiche((int) $id, $acces);

        if ($fiche === null) {
            Session::flash('error', 'Dossier introuvable, ou hors de votre périmètre.');
            $this->rediriger('colisage/envois');
        }

        $this->colisageView('colisage/envois/fiche', 'Dossier ' . $fiche['dossier']['numero'], 'envois_liste', [
            'envois' => $fiche,
        ]);
    }

    public function fichePdf(string $id): void
    {
        $acces = $this->acces();
        $envois = $this->service->fiche((int) $id, $acces);

        if ($envois === null) {
            Session::flash('error', 'Dossier introuvable, ou hors de votre périmètre.');
            $this->rediriger('colisage/envois');
        }

        $envois['edite_par'] = $this->nomUtilisateur();

        require BASE_PATH . '/views/colisage/envois/fiche_pdf.php';
    }

    public function deposerDocument(string $id): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;
        $this->exigerJeton($retour);

        $fichier = is_array($_FILES['fichier'] ?? null) ? $_FILES['fichier'] : [];

        // Au-delà de post_max_size, PHP vide $_POST et $_FILES sans prévenir.
        if ($fichier === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Session::flash('error', 'Le fichier est trop lourd pour être transmis : 10 Mo au maximum.');
            $this->rediriger($retour);
        }

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
            Session::flash('error', 'Soumission impossible, il reste à faire : ' . implode(' ', $e->erreurs));
        } catch (Throwable $e) {
            error_log('[Dossiers envoi] soumettre : ' . $e->getMessage());
            Session::flash('error', "Le dossier n'a pas pu être soumis. Réessayez.");
        }

        $this->rediriger($retour);
    }

    public function valider(string $id): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;
        $this->exigerJeton($retour);

        $this->agir($retour, fn (): string => $this->service->valider((int) $id, $acces), 'valider');
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

    public function reaffecter(string $id): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;
        $this->exigerJeton($retour);

        $this->agir($retour, fn (): string => $this->service->reaffecter((int) $id, $_POST['responsable_id'] ?? null, $acces), 'reaffecter');
    }

    public function annuler(string $id): void
    {
        $acces = $this->acces();
        $retour = 'colisage/envois/' . (int) $id;
        $this->exigerJeton($retour);

        $this->agir($retour, fn (): string => $this->service->annuler((int) $id, $_POST['motif'] ?? null, $acces), 'annuler');
    }

    // ------------------------------------------------------------------
    // Prestataires
    // ------------------------------------------------------------------

    public function prestataires(): void
    {
        $acces = $this->acces();

        $this->colisageView('colisage/envois/prestataires', 'Transporteurs et prestataires', 'envois_prestataires', [
            'envois' => ['prestataires' => $this->service->prestataires()] + $this->droitsGeneraux($acces),
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
            Session::flash('error', "Accès refusé : vous n'avez pas l'habilitation requise pour les dossiers d'envoi.");
            $this->rediriger('colisage/dashboard');
        }

        return $acces;
    }

    /** @return array{peut_creer:bool, voit_tout:bool, est_valideur:bool} */
    private function droitsGeneraux(DossierEnvoiAcces $acces): array
    {
        return ['peut_creer' => $acces->peutCreer(), 'voit_tout' => $acces->voitTout(), 'est_valideur' => $acces->estValideur()];
    }

    /**
     * @param array<string, mixed> $donnees
     * @param array<int, string> $erreurs
     */
    private function afficherFormulaire(array $donnees, array $erreurs, ?int $id): void
    {
        $referentiels = $this->service->referentiels(
            ($donnees['dossier']['expedition_id'] ?? null) !== null ? (int) $donnees['dossier']['expedition_id'] : null
        );

        $this->colisageView(
            'colisage/envois/formulaire',
            $id === null ? "Nouveau dossier d'envoi" : 'Modifier le dossier ' . ($donnees['dossier']['numero'] ?? ''),
            'envois_liste',
            ['envois' => $donnees + $referentiels + ['erreurs' => $erreurs, 'id' => $id]]
        );
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
