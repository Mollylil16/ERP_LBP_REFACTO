<?php

declare(strict_types=1);

namespace App\Controllers\Crm;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Crm\CrmRepository;
use App\Repositories\Shared\ModuleDashboardRepository;

final class CrmClientController extends CrmBaseController
{
    private CrmRepository $repo;
    private ModuleDashboardRepository $moduleRepo;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->repo = new CrmRepository($pdo);
        $this->moduleRepo = new ModuleDashboardRepository($pdo);
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'crm_status' => (string) ($_GET['crm_status'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->repo->listClients($filters, $page);

        $module = $this->moduleRepo->dashboardFor('crm');

        $this->crmView('crm/clients/index', 'Annuaire Clients', 'clients', $module, [
            'clients' => $result['clients'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::check();

        $module = $this->moduleRepo->dashboardFor('crm');
        $commercialOwners = $this->repo->commercialOwners();

        $this->crmView('crm/clients/create', 'Nouveau Client / Prospect', 'clients', $module, [
            'commercialOwners' => $commercialOwners,
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('crm/clients/nouveau'));
            exit;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            Session::flash('error', 'Le nom du client est obligatoire.');
            header('Location: ' . View::url('crm/clients/nouveau'));
            exit;
        }

        $id = $this->repo->createClient([
            'name' => $name,
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'type' => (string) ($_POST['type'] ?? 'standard'),
            'crm_status' => (string) ($_POST['crm_status'] ?? 'prospect'),
            'secteur_activite' => trim((string) ($_POST['secteur_activite'] ?? '')),
            'notes_commerciales' => trim((string) ($_POST['notes_commerciales'] ?? '')),
            'commercial_owner_id' => !empty($_POST['commercial_owner_id']) ? (int) $_POST['commercial_owner_id'] : null,
        ]);

        Session::flash('success', 'Client/prospect créé avec succès.');
        header('Location: ' . View::url('crm/clients/' . $id));
        exit;
    }

    public function show(int $id): void
    {
        AuthMiddleware::check();

        $client = $this->repo->findClient($id);
        if ($client === null) {
            Session::flash('error', 'Client introuvable.');
            header('Location: ' . View::url('crm/clients'));
            exit;
        }

        $module = $this->moduleRepo->dashboardFor('crm');

        $this->crmView('crm/clients/show', $client['name'], 'clients', $module, [
            'client' => $client,
            'colis' => $this->repo->clientColis($id),
            'factures' => $this->repo->clientFactures($id),
            'interactions' => $this->repo->clientInteractions($id),
            'opportunities' => $this->repo->clientOpportunities($id),
            'commercialOwners' => $this->repo->commercialOwners(),
        ]);
    }

    public function update(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('crm/clients/' . $id));
            exit;
        }

        $this->repo->updateClientCrm($id, [
            'crm_status' => (string) ($_POST['crm_status'] ?? 'prospect'),
            'secteur_activite' => trim((string) ($_POST['secteur_activite'] ?? '')),
            'notes_commerciales' => trim((string) ($_POST['notes_commerciales'] ?? '')),
            'commercial_owner_id' => !empty($_POST['commercial_owner_id']) ? (int) $_POST['commercial_owner_id'] : null,
        ]);

        Session::flash('success', 'Fiche client mise à jour.');
        header('Location: ' . View::url('crm/clients/' . $id));
        exit;
    }

    public function storeInteraction(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('crm/clients/' . $id));
            exit;
        }

        $subject = trim((string) ($_POST['subject'] ?? ''));
        if ($subject === '') {
            Session::flash('error', 'L\'objet de l\'interaction est obligatoire.');
            header('Location: ' . View::url('crm/clients/' . $id));
            exit;
        }

        $this->repo->createInteraction([
            'client_id' => $id,
            'user_id' => Auth::id(),
            'channel' => (string) ($_POST['channel'] ?? 'appel'),
            'subject' => $subject,
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'next_action_date' => !empty($_POST['next_action_date']) ? $_POST['next_action_date'] : null,
        ]);

        Session::flash('success', 'Interaction enregistrée.');
        header('Location: ' . View::url('crm/clients/' . $id));
        exit;
    }

    public function storeOpportunity(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('crm/clients/' . $id));
            exit;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            Session::flash('error', 'Le titre de l\'opportunité est obligatoire.');
            header('Location: ' . View::url('crm/clients/' . $id));
            exit;
        }

        $this->repo->createOpportunity([
            'client_id' => $id,
            'title' => $title,
            'stage' => (string) ($_POST['stage'] ?? 'qualification'),
            'estimated_amount' => trim((string) ($_POST['estimated_amount'] ?? '')),
            'currency' => (string) ($_POST['currency'] ?? 'XOF'),
            'expected_close_date' => !empty($_POST['expected_close_date']) ? $_POST['expected_close_date'] : null,
            'probability' => (string) ($_POST['probability'] ?? '10'),
        ]);

        Session::flash('success', 'Opportunité créée.');
        header('Location: ' . View::url('crm/clients/' . $id));
        exit;
    }

    public function updateOpportunityStage(int $opportunityId): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('crm/clients'));
            exit;
        }

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $stage = (string) ($_POST['stage'] ?? 'qualification');

        $this->repo->updateOpportunityStage($opportunityId, $stage);

        Session::flash('success', 'Étape de l\'opportunité mise à jour.');
        header('Location: ' . View::url('crm/clients/' . $clientId));
        exit;
    }
}
