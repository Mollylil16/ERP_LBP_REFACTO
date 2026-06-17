<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Repositories\LogistiqueRepository;
use App\Security\OperationPolicy;

/**
 * Contrôleur du module Logistique Interne & Exploitation.
 * Gère : Prestataires, Factures, Retraits Hub, Fournitures, Crédits inter-agences.
 */
final class LogistiqueController extends BaseController
{
    private LogistiqueRepository $repo;

    public function __construct()
    {
        $this->repo = new LogistiqueRepository();
    }

    private function viewData(string $title, string $active, array $extra = []): array
    {
        return array_merge([
            'pageTitle'         => $title,
            'moduleName'        => 'Logistique Interne',
            'moduleCode'        => 'LOG',
            'activeModule'      => $active,
            'additionalStyles'  => ['css/finea-ui.css', 'css/components.css'],
            'additionalScripts' => ['js/components.js'],
        ], $extra);
    }

    // ─────────────────────────────────────────────
    //  DASHBOARD
    // ─────────────────────────────────────────────

    public function dashboard(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::LOGISTIQUE_FOURNITURES_VIEW);

        $kpis = $this->repo->getDashboardKpis();

        $this->view('logistique/dashboard', $this->viewData('Logistique Interne', 'dashboard', [
            'kpis' => $kpis,
        ]));
    }

    // ─────────────────────────────────────────────
    //  PRESTATAIRES
    // ─────────────────────────────────────────────

    public function prestataires(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::TRANSIT_PRESTATAIRES_VIEW);

        $filters = [
            'type'   => $_GET['type'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];

        $prestataires = $this->repo->getAllPrestataires($filters);

        $this->view('logistique/prestataires/index', $this->viewData('Prestataires & Partenaires', 'prestataires', [
            'prestataires' => $prestataires,
            'filters'      => $filters,
        ]));
    }

    public function createPrestataire(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::TRANSIT_PRESTATAIRES_MANAGE);

        $this->view('logistique/prestataires/create', $this->viewData('Nouveau Prestataire', 'prestataires'));
    }

    public function storePrestataire(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::TRANSIT_PRESTATAIRES_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/logistique/prestataires/nouveau');
        }

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            Session::flash('error', 'Le nom du prestataire est obligatoire.');
            $this->redirect('/logistique/prestataires/nouveau');
        }

        $this->repo->createPrestataire([
            'type'         => $_POST['type'] ?? 'AUTRE',
            'name'         => $name,
            'contact_info' => trim($_POST['contact_info'] ?? ''),
            'country'      => trim($_POST['country'] ?? ''),
            'contact_name' => trim($_POST['contact_name'] ?? ''),
            'phone'        => trim($_POST['phone'] ?? ''),
            'email'        => trim($_POST['email'] ?? ''),
        ]);

        Session::flash('success', 'Prestataire créé avec succès.');
        $this->redirect('/logistique/prestataires');
    }

    // ─────────────────────────────────────────────
    //  FACTURES
    // ─────────────────────────────────────────────

    public function factures(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::FACTURATION_FACTURES_VIEW);

        $filters = [
            'status'        => $_GET['status'] ?? '',
            'prestataire_id'=> $_GET['prestataire_id'] ?? '',
        ];

        $factures     = $this->repo->getAllFactures($filters);
        $prestataires = $this->repo->getAllPrestataires();

        $this->view('logistique/factures/index', $this->viewData('Factures Prestataires', 'factures', [
            'factures'     => $factures,
            'prestataires' => $prestataires,
            'filters'      => $filters,
        ]));
    }

    public function createFacture(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::FACTURATION_FACTURES_MANAGE);

        $prestataires = $this->repo->getAllPrestataires();

        $this->view('logistique/factures/create', $this->viewData('Nouvelle Facture', 'factures', [
            'prestataires' => $prestataires,
        ]));
    }

    public function storeFacture(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::FACTURATION_FACTURES_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/logistique/factures/nouvelle');
        }

        $data = [
            'prestataire_id' => (int)($_POST['prestataire_id'] ?? 0),
            'invoice_number' => trim($_POST['invoice_number'] ?? ''),
            'amount'         => (float)($_POST['amount'] ?? 0),
            'currency'       => $_POST['currency'] ?? 'XOF',
            'due_date'       => $_POST['due_date'] ?? '',
            'lta_number'     => trim($_POST['lta_number'] ?? ''),
            'issue_date'     => $_POST['issue_date'] ?? '',
            'notes'          => trim($_POST['notes'] ?? ''),
        ];

        if ($data['prestataire_id'] <= 0 || empty($data['invoice_number']) || $data['amount'] <= 0) {
            Session::flash('error', 'Prestataire, numéro et montant de la facture sont obligatoires.');
            $this->redirect('/logistique/factures/nouvelle');
        }

        $this->repo->createFacture($data);

        Session::flash('success', 'Facture enregistrée.');
        $this->redirect('/logistique/factures');
    }

    public function showFacture(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::FACTURATION_FACTURES_VIEW);

        $facture = $this->repo->findFacture($id);
        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            $this->redirect('/logistique/factures');
        }

        $retraits = $this->repo->getRetraitsOfFacture($id);

        $this->view('logistique/factures/show', $this->viewData('Facture ' . $facture['invoice_number'], 'factures', [
            'facture' => $facture,
            'retraits'=> $retraits,
        ]));
    }

    // ─────────────────────────────────────────────
    //  RETRAITS HUB
    // ─────────────────────────────────────────────

    public function retraits(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::FINANCE_RETRAITS_VIEW);

        $filters = ['status' => $_GET['status'] ?? ''];
        $retraits = $this->repo->getAllRetraits($filters);

        $this->view('logistique/retraits/index', $this->viewData('Retraits Hub', 'retraits', [
            'retraits' => $retraits,
            'filters'  => $filters,
        ]));
    }

    public function createRetrait(int $factureId): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::FINANCE_RETRAITS_MANAGE);

        $facture = $this->repo->findFacture($factureId);
        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            $this->redirect('/logistique/factures');
        }

        $this->view('logistique/retraits/create', $this->viewData('Demande de Retrait Hub', 'retraits', [
            'facture' => $facture,
        ]));
    }

    public function storeRetrait(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::FINANCE_RETRAITS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/logistique/retraits');
        }

        $factureId = (int)($_POST['facture_id'] ?? 0);
        $facture = $this->repo->findFacture($factureId);
        if (!$facture) {
            Session::flash('error', 'Facture invalide.');
            $this->redirect('/logistique/retraits');
        }

        $this->repo->createRetrait([
            'facture_id'            => $factureId,
            'amount_paid'           => (float)($_POST['amount_paid'] ?? 0),
            'currency'              => $facture['currency'],
            'recorded_by'           => Auth::id(),
            'reference_transaction' => trim($_POST['reference_transaction'] ?? ''),
            'notes'                 => trim($_POST['notes'] ?? ''),
        ]);

        Session::flash('success', 'Demande de retrait soumise pour approbation.');
        $this->redirect('/logistique/retraits');
    }

    public function approuverRetrait(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::FINANCE_RETRAITS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/logistique/retraits');
        }

        $this->repo->approuverRetrait($id, Auth::id() ?? 0);
        Session::flash('success', 'Retrait approuvé et paiement enregistré.');
        $this->redirect('/logistique/retraits');
    }

    public function refuserRetrait(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::FINANCE_RETRAITS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/logistique/retraits');
        }

        $reason = trim($_POST['rejection_reason'] ?? '');
        $this->repo->refuserRetrait($id, Auth::id() ?? 0, $reason);
        Session::flash('error', 'Retrait refusé.');
        $this->redirect('/logistique/retraits');
    }

    // ─────────────────────────────────────────────
    //  FOURNITURES
    // ─────────────────────────────────────────────

    public function fournitures(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::LOGISTIQUE_FOURNITURES_VIEW);

        $filters = [
            'status'    => $_GET['status'] ?? '',
            'agency_id' => $_GET['agency_id'] ?? '',
        ];

        $fournitures = $this->repo->getAllFournitures($filters);
        $agencies    = $this->repo->getAgencies();

        $this->view('logistique/fournitures/index', $this->viewData('Demandes de Fournitures', 'fournitures', [
            'fournitures' => $fournitures,
            'agencies'    => $agencies,
            'filters'     => $filters,
        ]));
    }

    public function createFourniture(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::LOGISTIQUE_FOURNITURES_VIEW);

        $agencies = $this->repo->getAgencies();

        $this->view('logistique/fournitures/create', $this->viewData('Nouvelle Demande de Fournitures', 'fournitures', [
            'agencies' => $agencies,
        ]));
    }

    public function storeFourniture(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::LOGISTIQUE_FOURNITURES_VIEW);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/logistique/fournitures/nouvelle');
        }

        $agencyId = (int)($_POST['agency_id'] ?? 0);
        $items    = trim($_POST['items_requested'] ?? '');

        if ($agencyId <= 0 || empty($items)) {
            Session::flash('error', 'Agence et liste des articles sont obligatoires.');
            $this->redirect('/logistique/fournitures/nouvelle');
        }

        $this->repo->createFourniture([
            'agency_id'       => $agencyId,
            'requested_by'    => Auth::id(),
            'items_requested' => $items,
        ]);

        Session::flash('success', 'Demande de fournitures soumise.');
        $this->redirect('/logistique/fournitures');
    }

    public function validerFourniture(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::LOGISTIQUE_FOURNITURES_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/logistique/fournitures');
        }

        $this->repo->validerFourniture($id, Auth::id() ?? 0);
        Session::flash('success', 'Demande validée.');
        $this->redirect('/logistique/fournitures');
    }

    public function livrerFourniture(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::LOGISTIQUE_FOURNITURES_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/logistique/fournitures');
        }

        $this->repo->livrerFourniture($id);
        Session::flash('success', 'Fournitures marquées comme livrées.');
        $this->redirect('/logistique/fournitures');
    }

    public function rejeterFourniture(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::LOGISTIQUE_FOURNITURES_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/logistique/fournitures');
        }

        $reason = trim($_POST['rejection_reason'] ?? '');
        $this->repo->rejeterFourniture($id, Auth::id() ?? 0, $reason);
        Session::flash('error', 'Demande rejetée.');
        $this->redirect('/logistique/fournitures');
    }

    private function getAgencies(): array
    {
        return $this->repo->getAgencies();
    }
}
