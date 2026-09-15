<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Security\DossierEnvoiAcces;
use App\Security\ModuleAccess;
use App\Services\Colisage\DossierEnvoiService;
use App\Services\Colisage\PointageColisService;
use Throwable;

/**
 * Pointage des colis : l'agence d'arrivée coche ce qu'elle reçoit, la direction
 * suit l'ensemble. Le départ se prépare dans DossierEnvoiController : les colis
 * partent avec le document de la compagnie saisi par l'agent export.
 *
 * Périmètre : un utilisateur rattaché à une agence n'agit que sur les départs
 * et réceptions de son agence. Les rôles à portée réseau (administration, DG,
 * superviseur général, comptable) voient toutes les agences et choisissent
 * celle sur laquelle ils travaillent.
 */
final class PointageColisController extends ColisageBaseController
{
    private PointageColisService $service;

    public function __construct()
    {
        $this->service = PointageColisService::creer();
    }

    public function reception(): void
    {
        AuthMiddleware::check();

        $perimetre = ModuleAccess::agenceVisible();
        $agenceId = $perimetre ?? $this->agenceDemandee();

        $this->colisageView('colisage/pointage/reception', 'Réception des colis', 'pointage_reception', [
            'pointage' => [
                'agences' => $perimetre === null ? $this->service->agences() : [],
                'agence_id' => $agenceId,
                'peut_choisir' => $perimetre === null,
                'departs' => $agenceId === 0 ? [] : $this->service->departsAttendus($agenceId),
            ],
        ]);
    }

    public function pointer(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Session expirée : rechargez la page avant de continuer.']);
        }

        try {
            $this->json($this->service->pointer(
                (int) ($_POST['colis_id'] ?? 0),
                (string) ($_POST['recu'] ?? '') === '1',
                ModuleAccess::agenceVisible(),
                Auth::id()
            ));
        } catch (Throwable $e) {
            error_log('[Pointage colis] pointer : ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => "Ce pointage n'a pas pu être enregistré. Réessayez."]);
        }
    }

    public function scanner(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Session expirée : rechargez la page avant de continuer.']);
        }

        $perimetre = ModuleAccess::agenceVisible();
        $agence = $perimetre ?? (int) ($_POST['agence_id'] ?? 0);

        try {
            $this->json($this->service->scanner((string) ($_POST['code'] ?? ''), $agence > 0 ? $agence : null, Auth::id()));
        } catch (Throwable $e) {
            error_log('[Pointage colis] scanner : ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => "Ce scan n'a pas pu être enregistré. Réessayez."]);
        }
    }

    public function toutPointer(string $id): void
    {
        AuthMiddleware::check();

        $retour = $this->retourReception();
        $this->exigerJeton($retour);

        try {
            $resultat = $this->service->toutPointer((int) $id, ModuleAccess::agenceVisible(), Auth::id());
            Session::flash($resultat['ok'] ? 'success' : 'error', $resultat['message']);
        } catch (Throwable $e) {
            error_log('[Pointage colis] toutPointer : ' . $e->getMessage());
            Session::flash('error', "Le pointage n'a pas pu être enregistré. Aucun colis n'a été modifié.");
        }

        $this->rediriger($retour);
    }

    public function prevenirClients(string $id): void
    {
        AuthMiddleware::check();

        $retour = $this->retourReception();
        $this->exigerJeton($retour);

        $resultat = $this->service->prevenirClients((int) $id, ModuleAccess::agenceVisible());
        Session::flash($resultat['ok'] ? 'success' : 'info', $resultat['message']);

        $this->rediriger($retour);
    }

    public function suivi(): void
    {
        AuthMiddleware::check();

        $perimetre = ModuleAccess::agenceVisible();
        $agenceId = $perimetre ?? $this->agenceDemandee();

        $au = $this->dateValide($_GET['au'] ?? '', date('Y-m-d'));
        $du = $this->dateValide($_GET['du'] ?? '', date('Y-m-d', (int) strtotime('-30 days')));
        if ($du > $au) {
            [$du, $au] = [$au, $du];
        }

        $suivi = $agenceId === 0
            ? ['departs' => [], 'totaux' => ['departs' => 0, 'envoyes' => 0, 'recus' => 0, 'manquants' => 0, 'en_attente' => 0], 'trajets' => [], 'hors_liste' => []]
            : $this->service->suivi($du, $au, $agenceId);

        // Les numéros de dossier d'envoi ne s'affichent qu'à ceux qui peuvent les ouvrir.
        $dossiersParDepart = DossierEnvoiAcces::courant()->peutOuvrir()
            ? DossierEnvoiService::creer()->dossiersDesDeparts(array_map(static fn (array $d): int => (int) $d['id'], $suivi['departs']))
            : null;

        $this->colisageView('colisage/pointage/suivi', 'Suivi des départs', 'pointage_suivi', [
            'pointage' => [
                'du' => $du,
                'au' => $au,
                'agences' => $perimetre === null ? $this->service->agences() : [],
                'agence_id' => $agenceId,
                'peut_choisir' => $perimetre === null,
                'suivi' => $suivi,
                'dossiers_par_depart' => $dossiersParDepart,
            ],
        ]);
    }

    public function detail(string $id): void
    {
        AuthMiddleware::check();

        $detail = $this->service->detailDepart((int) $id, ModuleAccess::agenceVisible());

        if ($detail === null) {
            Session::flash('error', 'Départ introuvable, ou sans rapport avec votre agence.');
            $this->rediriger('colisage/suivi-departs');
        }

        if (DossierEnvoiAcces::courant()->peutOuvrir()) {
            $detail['dossiers_envoi'] = DossierEnvoiService::creer()->dossiersDesDeparts([(int) $id])[(int) $id] ?? [];
        }

        $this->colisageView('colisage/pointage/detail', 'Départ ' . $detail['depart']['reference'], 'pointage_suivi', [
            'pointage' => $detail,
        ]);
    }

    // ------------------------------------------------------------------

    private function agenceDemandee(): ?int
    {
        $agence = (int) ($_GET['agence'] ?? 0);

        return $agence > 0 ? $agence : null;
    }

    private function retourReception(): string
    {
        $filtre = (int) ($_POST['agence_filtre'] ?? 0);

        return 'colisage/reception' . (ModuleAccess::agenceVisible() === null && $filtre > 0 ? '?agence=' . $filtre : '');
    }

    private function exigerJeton(string $retour): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide. Veuillez réessayer.');
            $this->rediriger($retour);
        }
    }

    private function dateValide(mixed $valeur, string $defaut): string
    {
        $valeur = (string) $valeur;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) === 1 && strtotime($valeur) !== false ? $valeur : $defaut;
    }

    private function rediriger(string $chemin): never
    {
        header('Location: ' . View::url($chemin));
        exit;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function json(array $donnees): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
