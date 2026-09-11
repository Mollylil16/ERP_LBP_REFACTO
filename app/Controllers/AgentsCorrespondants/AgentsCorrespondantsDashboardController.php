<?php

declare(strict_types=1);

namespace App\Controllers\AgentsCorrespondants;

use App\Controllers\BaseController;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use App\Repositories\AgentsCorrespondants\AgentsCorrespondantsDashboardRepository;
use App\Security\ModuleAccess;
use App\Services\AgentsCorrespondants\AgentsCorrespondantsDashboardService;

final class AgentsCorrespondantsDashboardController extends BaseController
{
    private const SLUG = 'agents-correspondants';
    private const RETOUR = 'agents-correspondants/dashboard';

    private AgentsCorrespondantsDashboardService $service;

    public function __construct()
    {
        $this->service = new AgentsCorrespondantsDashboardService(
            new AgentsCorrespondantsDashboardRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        RoleMiddleware::check(ModuleAccess::rolesLecture(self::SLUG));

        $peutGerer = ModuleAccess::peutGerer(self::SLUG);
        $recherche = trim((string) ($_GET['q'] ?? ''));

        // La fiche à modifier n'est chargée que pour qui a le droit d'écrire :
        // sinon le formulaire n'est même pas rendu.
        $enEdition = null;
        if ($peutGerer && !empty($_GET['modifier'])) {
            $enEdition = $this->service->trouver((int) $_GET['modifier']);
            if ($enEdition === null) {
                Session::flash('error', 'Correspondant introuvable.');
            }
        }

        $module = $this->service->dashboard();

        $this->view('agents_correspondants/dashboard', [
            'pageTitle' => 'Agents & Correspondants',
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => (array) $module['navigation'],
            'additionalStyles' => ['css/finea-ui.css'],
            'donnees' => $this->service->reseau($recherche),
            'peutGerer' => $peutGerer,
            'enEdition' => $enEdition,
        ]);
    }

    public function store(): void
    {
        $donnees = $this->donneesValidees();

        $id = $this->service->creer($donnees);

        Session::flash('success', 'Correspondant « ' . $donnees['name'] . ' » créé.');
        $this->retour('?modifier=' . $id);
    }

    public function update(int $id): void
    {
        $donnees = $this->donneesValidees($id);

        if ($this->service->trouver($id) === null) {
            Session::flash('error', 'Correspondant introuvable.');
            $this->retour();
        }

        $this->service->modifier($id, $donnees);

        Session::flash('success', 'Fiche mise à jour.');
        $this->retour();
    }

    public function deactivate(int $id): void
    {
        $this->garderEcriture();

        if ($this->service->trouver($id) === null) {
            Session::flash('error', 'Correspondant introuvable.');
            $this->retour();
        }

        $this->service->desactiver($id);

        Session::flash('success', 'Correspondant désactivé. Sa fiche reste consultable.');
        $this->retour();
    }

    /**
     * Contrôle d'accès, jeton CSRF et validation métier, dans cet ordre.
     *
     * Le jeton est vérifié après le contrôle de rôle : un utilisateur sans
     * habilitation doit être refusé pour ce motif, pas pour un jeton expiré.
     *
     * @return array<string, mixed>
     */
    private function donneesValidees(?int $idEnCours = null): array
    {
        $this->garderEcriture();

        $donnees = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'country' => trim((string) ($_POST['country'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'contact_name' => trim((string) ($_POST['contact_name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'coverage' => trim((string) ($_POST['coverage'] ?? '')),
            'is_active' => !empty($_POST['is_active']),
        ];

        $erreurs = $this->service->erreurs($donnees);
        if ($erreurs !== []) {
            Session::flash('error', implode(' ', $erreurs));
            $this->retour($idEnCours !== null ? '?modifier=' . $idEnCours : '');
        }

        return $donnees;
    }

    private function garderEcriture(): void
    {
        RoleMiddleware::check(ModuleAccess::rolesGestion(self::SLUG));

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            $this->retour();
        }
    }

    private function retour(string $suffixe = ''): never
    {
        header('Location: ' . View::url(self::RETOUR) . $suffixe . '#formulaire');
        exit;
    }
}
