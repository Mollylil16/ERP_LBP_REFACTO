<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\Auth;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Repositories\ColisageRepository;
use App\Security\OperationPolicy;

/**
 * Contrôleur principal du module Colisage & Logistique Fret.
 * Gère : Dashboard, Colis (CRUD + retrait), Expéditions, Tracking, Inventaires.
 */
final class ColisageController extends BaseController
{
    private ColisageRepository $repo;

    public function __construct()
    {
        $this->repo = new ColisageRepository();
    }

    // ─────────────────────────────────────────────
    //  COMMUN
    // ─────────────────────────────────────────────

    private function viewData(string $title, string $active, array $extra = []): array
    {
        return array_merge([
            'pageTitle'         => $title,
            'moduleName'        => 'Colisage & Fret',
            'moduleCode'        => 'COL',
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
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_VIEW);

        $counts = $this->repo->countByStatus();

        $this->view('colisage/dashboard', $this->viewData('Colisage & Fret', 'dashboard', [
            'counts' => $counts,
        ]));
    }

    // ─────────────────────────────────────────────
    //  COLIS
    // ─────────────────────────────────────────────

    public function index(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_VIEW);

        $filters = [
            'status'    => $_GET['status'] ?? '',
            'search'    => $_GET['search'] ?? '',
            'agency_id' => $_GET['agency_id'] ?? '',
        ];

        $colisList = $this->repo->getAllColis($filters);
        $agencies  = $this->repo->getAgencies();

        $this->view('colisage/colis/index', $this->viewData('Gestion des Colis', 'colis', [
            'colisList' => $colisList,
            'agencies'  => $agencies,
            'filters'   => $filters,
        ]));
    }

    public function show(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_VIEW);

        $colis = $this->repo->findById($id);
        if (!$colis) {
            Session::flash('error', 'Colis introuvable.');
            $this->redirect('/colisage/colis');
        }

        $marchandises = $this->repo->getMarchandises($id);
        $tracking     = $this->repo->getTrackingHistory($id);

        $this->view('colisage/colis/show', $this->viewData('Colis ' . $colis['tracking_number'], 'colis', [
            'colis'       => $colis,
            'marchandises'=> $marchandises,
            'tracking'    => $tracking,
        ]));
    }

    public function create(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_MANAGE);

        $clients  = $this->repo->getClients();
        $agencies = $this->repo->getAgencies();

        $this->view('colisage/colis/create', $this->viewData('Nouveau Colis', 'colis', [
            'clients'  => $clients,
            'agencies' => $agencies,
        ]));
    }

    public function store(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée, veuillez recommencer.');
            $this->redirect('/colisage/colis/nouveau');
        }

        $data = [
            'sender_id'           => (int)($_POST['sender_id'] ?? 0),
            'receiver_id'         => (int)($_POST['receiver_id'] ?? 0),
            'departure_agency_id' => (int)($_POST['departure_agency_id'] ?? 0),
            'arrival_agency_id'   => (int)($_POST['arrival_agency_id'] ?? 0),
            'total_weight'        => (float)($_POST['total_weight'] ?? 0),
            'declared_value'      => (float)($_POST['declared_value'] ?? 0),
            'total_price'         => (float)($_POST['total_price'] ?? 0),
            'currency'            => $_POST['currency'] ?? 'XOF',
            'description'         => trim($_POST['description'] ?? ''),
            'notes'               => trim($_POST['notes'] ?? ''),
        ];

        if ($data['sender_id'] <= 0 || $data['receiver_id'] <= 0) {
            Session::flash('error', 'Expéditeur et destinataire sont obligatoires.');
            $this->redirect('/colisage/colis/nouveau');
        }

        $colisId = $this->repo->createColis($data);

        // Marchandises (lignes dynamiques)
        $descriptions = $_POST['marchandise_description'] ?? [];
        $quantities   = $_POST['marchandise_quantity'] ?? [];
        $weights      = $_POST['marchandise_weight'] ?? [];

        foreach ($descriptions as $i => $desc) {
            if (trim((string)$desc) === '') continue;
            $this->repo->addMarchandise($colisId, [
                'description' => $desc,
                'quantity'    => (int)($quantities[$i] ?? 1),
                'unit_weight' => (float)($weights[$i] ?? 0),
            ]);
        }

        // Ajouter un événement tracking initial
        $this->repo->addTrackingEvent($colisId, [
            'step_name'   => 'Réception en agence',
            'status'      => 'RECEPTIONNE',
            'recorded_by' => Auth::id(),
        ]);

        Session::flash('success', 'Colis créé avec succès. Numéro de tracking assigné.');
        $this->redirect('/colisage/colis/' . $colisId);
    }

    public function showRetrait(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_MANAGE);

        $colis = $this->repo->findById($id);
        if (!$colis || $colis['status'] === 'RETIRE') {
            Session::flash('error', 'Colis introuvable ou déjà retiré.');
            $this->redirect('/colisage/colis/' . $id);
        }

        $this->view('colisage/colis/retrait', $this->viewData('Remise Colis', 'colis', [
            'colis' => $colis,
        ]));
    }

    public function processRetrait(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/colisage/colis/' . $id . '/retrait');
        }

        $data = [
            'retrieval_name'  => trim($_POST['retrieval_name'] ?? ''),
            'retrieval_cni'   => trim($_POST['retrieval_cni'] ?? ''),
            'retrieval_phone' => trim($_POST['retrieval_phone'] ?? ''),
            'retrieved_by'    => Auth::id(),
        ];

        if (empty($data['retrieval_name']) || empty($data['retrieval_cni'])) {
            Session::flash('error', 'Nom et numéro de CNI du récupérateur sont obligatoires.');
            $this->redirect('/colisage/colis/' . $id . '/retrait');
        }

        $this->repo->marquerRetire($id, $data);

        $this->repo->addTrackingEvent($id, [
            'step_name'   => 'Remise au destinataire - CNI: ' . $data['retrieval_cni'],
            'status'      => 'LIVRE',
            'recorded_by' => Auth::id(),
        ]);

        Session::flash('success', 'Colis marqué comme retiré avec succès.');
        $this->redirect('/colisage/colis/' . $id);
    }

    public function addTrackingEvent(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/colisage/colis/' . $id);
        }

        $this->repo->addTrackingEvent($id, [
            'step_name'   => trim($_POST['step_name'] ?? ''),
            'status'      => trim($_POST['status'] ?? 'INFO'),
            'latitude'    => $_POST['latitude'] ?? null,
            'longitude'   => $_POST['longitude'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        Session::flash('success', 'Étape de tracking ajoutée.');
        $this->redirect('/colisage/colis/' . $id);
    }

    // ─────────────────────────────────────────────
    //  EXPÉDITIONS
    // ─────────────────────────────────────────────

    public function expeditions(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_EXPEDITIONS_VIEW);

        $filters = [
            'status'         => $_GET['status'] ?? '',
            'transport_type' => $_GET['transport_type'] ?? '',
        ];

        $expeditions = $this->repo->getAllExpeditions($filters);

        $this->view('colisage/expeditions/index', $this->viewData('Expéditions / Manifestes', 'expeditions', [
            'expeditions' => $expeditions,
            'filters'     => $filters,
        ]));
    }

    public function createExpedition(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_EXPEDITIONS_MANAGE);

        $agencies  = $this->repo->getAgencies();
        $livreurs  = $this->repo->getLivreurs();
        $colisDisponibles = $this->repo->getColisReadyForExpedition();

        $this->view('colisage/expeditions/create', $this->viewData('Nouvelle Expédition', 'expeditions', [
            'agencies'         => $agencies,
            'livreurs'         => $livreurs,
            'colisDisponibles' => $colisDisponibles,
        ]));
    }

    public function storeExpedition(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_EXPEDITIONS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/colisage/expeditions/nouveau');
        }

        $data = [
            'transport_type'         => $_POST['transport_type'] ?? 'TERRESTRE',
            'departure_agency_id'    => (int)($_POST['departure_agency_id'] ?? 0),
            'arrival_agency_id'      => (int)($_POST['arrival_agency_id'] ?? 0),
            'departure_date'         => $_POST['departure_date'] ?? '',
            'estimated_arrival_date' => $_POST['estimated_arrival_date'] ?? '',
            'driver_user_id'         => (int)($_POST['driver_user_id'] ?? 0) ?: null,
            'notes'                  => trim($_POST['notes'] ?? ''),
        ];

        if ($data['departure_agency_id'] <= 0 || $data['arrival_agency_id'] <= 0) {
            Session::flash('error', 'Agences de départ et d\'arrivée obligatoires.');
            $this->redirect('/colisage/expeditions/nouveau');
        }

        $expeditionId = $this->repo->createExpedition($data);

        // Assigner les colis sélectionnés
        $colisIds = $_POST['colis_ids'] ?? [];
        $assignes = 0;
        foreach ($colisIds as $colisId) {
            if ($this->repo->assignColisToExpedition($expeditionId, (int)$colisId)) {
                $assignes++;
            }
        }

        Session::flash('success', "Expédition créée avec {$assignes} colis assigné(s).");
        $this->redirect('/colisage/expeditions/' . $expeditionId);
    }

    public function showExpedition(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_EXPEDITIONS_VIEW);

        $expedition = $this->repo->findExpeditionById($id);
        if (!$expedition) {
            Session::flash('error', 'Expédition introuvable.');
            $this->redirect('/colisage/expeditions');
        }

        $colis            = $this->repo->getColisOfExpedition($id);
        $colisDisponibles = $this->repo->getColisReadyForExpedition();

        $this->view('colisage/expeditions/show', $this->viewData('Expédition ' . $expedition['reference'], 'expeditions', [
            'expedition'       => $expedition,
            'colis'            => $colis,
            'colisDisponibles' => $colisDisponibles,
        ]));
    }

    public function updateExpeditionStatus(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_EXPEDITIONS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/colisage/expeditions/' . $id);
        }

        $newStatus = $_POST['status'] ?? '';
        $allowed = ['PLANIFIE', 'EN_COURS', 'ARRIVE', 'CLOTURE'];

        if (!in_array($newStatus, $allowed, true)) {
            Session::flash('error', 'Statut invalide.');
            $this->redirect('/colisage/expeditions/' . $id);
        }

        $this->repo->updateExpeditionStatus($id, $newStatus);

        if ($newStatus === 'EN_COURS') {
            // Ajouter événement tracking sur tous les colis
            foreach ($this->repo->getColisOfExpedition($id) as $c) {
                $this->repo->addTrackingEvent((int)$c['id'], [
                    'step_name'   => 'Départ de l\'expédition',
                    'status'      => 'EN_TRANSIT',
                    'recorded_by' => Auth::id(),
                ]);
            }
        }

        Session::flash('success', 'Statut de l\'expédition mis à jour.');
        $this->redirect('/colisage/expeditions/' . $id);
    }

    public function assignColisExpedition(int $expeditionId): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_EXPEDITIONS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/colisage/expeditions/' . $expeditionId);
        }

        $colisId = (int)($_POST['colis_id'] ?? 0);
        if ($this->repo->assignColisToExpedition($expeditionId, $colisId)) {
            Session::flash('success', 'Colis ajouté à l\'expédition.');
        } else {
            Session::flash('error', 'Ce colis est déjà assigné à une expédition.');
        }

        $this->redirect('/colisage/expeditions/' . $expeditionId);
    }

    // ─────────────────────────────────────────────
    //  INVENTAIRES
    // ─────────────────────────────────────────────

    public function inventaires(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_VIEW);

        $inventaires = $this->repo->getAllInventaires();

        $this->view('colisage/inventaire/index', $this->viewData('Inventaires d\'entrepôt', 'inventaire', [
            'inventaires' => $inventaires,
        ]));
    }

    public function createInventaire(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_MANAGE);

        $agencies = $this->repo->getAgencies();

        $this->view('colisage/inventaire/create', $this->viewData('Nouvel Inventaire', 'inventaire', [
            'agencies' => $agencies,
        ]));
    }

    public function storeInventaire(): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/colisage/inventaire/nouveau');
        }

        $agencyId = (int)($_POST['agency_id'] ?? 0);
        if ($agencyId <= 0) {
            Session::flash('error', 'Veuillez sélectionner une agence.');
            $this->redirect('/colisage/inventaire/nouveau');
        }

        $inventaireId = $this->repo->createInventaire($agencyId, Auth::id() ?? 0);

        Session::flash('success', 'Campagne d\'inventaire créée.');
        $this->redirect('/colisage/inventaire/' . $inventaireId);
    }

    public function showInventaire(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_VIEW);

        $inventaire = $this->repo->findInventaireById($id);
        if (!$inventaire) {
            Session::flash('error', 'Inventaire introuvable.');
            $this->redirect('/colisage/inventaire');
        }

        $lignes = $this->repo->getLignesInventaire($id);

        $this->view('colisage/inventaire/show', $this->viewData('Inventaire #' . $id, 'inventaire', [
            'inventaire' => $inventaire,
            'lignes'     => $lignes,
        ]));
    }

    public function scanInventaire(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/colisage/inventaire/' . $id);
        }

        $tracking = trim($_POST['tracking_number'] ?? '');
        $statusScan = $_POST['scan_status'] ?? 'PRESENT';
        $comments = trim($_POST['comments'] ?? '');

        $colis = $this->repo->findByTracking($tracking);
        if (!$colis) {
            Session::flash('error', 'Numéro de tracking introuvable : ' . $tracking);
            $this->redirect('/colisage/inventaire/' . $id);
        }

        $this->repo->scanColisInventaire($id, (int)$colis['id'], $statusScan, $comments ?: null);

        Session::flash('success', 'Colis ' . $tracking . ' scanné : ' . $statusScan);
        $this->redirect('/colisage/inventaire/' . $id);
    }

    public function cloturerInventaire(int $id): void
    {
        AuthMiddleware::check();
        PermissionMiddleware::checkOperation(OperationPolicy::COLISAGE_COLIS_MANAGE);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/colisage/inventaire/' . $id);
        }

        $this->repo->cloturerInventaire($id);

        Session::flash('success', 'Inventaire clôturé.');
        $this->redirect('/colisage/inventaire/' . $id);
    }
}
