<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Models\Database;
use App\Repositories\Colisage\ColisageRepository;
use App\Services\Colisage\ColisageService;
use App\Services\Shared\AuditLogService;
use App\Services\Shared\IntegrityRuleEngine;
use App\Helpers\View;

use App\View\Pages\Colisage\ColisageIndexPage;

final class ColisageController extends ColisageBaseController
{
    private ColisageService $service;

    public function __construct()
    {
        $this->service = new ColisageService(new ColisageRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $filters = [
            'q' => $_GET['q'] ?? '',
            'statut' => $_GET['statut'] ?? '',
            'type_expediteur' => $_GET['type_expediteur'] ?? '',
            'agence_id' => $_GET['agence_id'] ?? '',
        ];

        // Scope restriction : les utilisateurs locaux ne voient que leur agence (départ/arrivée)
        $userAgId = Auth::agenceId();
        $isGlobalRole = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg', 'caissiere_principale', 'comptable', 'superviseur_general']);

        if (!$isGlobalRole && $userAgId !== null && $userAgId > 0) {
            $filters['agence_id'] = $userAgId;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));

        $data = $this->service->listParcels($filters, $page);

        // Fetch sites/agences
        $sitesStmt = Database::getConnection()->query("SELECT id, name FROM company_sites WHERE is_active = 1");
        $sites = $sitesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->colisageView('colisage/parcels/index', 'Gestion des Colis', 'operations', [
            'page' => new ColisageIndexPage(array_replace($data, ['filters' => $filters]), $sites),
        ]);
    }

    /**
     * Formulaire de saisie d'un colis. Sans paramètre : flux générique historique
     * "Opérations (Colis)". Avec un code de trajet (sous-menu Opération à trajet fixe,
     * ex: LB-CI) : exactement le même formulaire, avec le trajet verrouillé et non
     * modifiable par l'agent (Règle 3.4 du cahier des charges).
     */
    public function create(?string $code = null): void
    {
        AuthMiddleware::check();

        $trajet = null;
        if ($code !== null) {
            $code = strtoupper(trim($code));
            $stmt = Database::getConnection()->prepare("SELECT * FROM trajets WHERE code = :code AND actif = 1 LIMIT 1");
            $stmt->execute(['code' => $code]);
            $trajet = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            if ($trajet === null) {
                Session::flash('error', "Le trajet spécifié ('{$code}') n'existe pas ou est inactif.");
                header('Location: ' . View::url('colisage/parcels'));
                exit;
            }
        }

        // Get sites
        $sitesStmt = Database::getConnection()->query("SELECT id, name FROM company_sites WHERE is_active = 1");
        $sites = $sitesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Get clients
        $clients = $this->service->listClients();

        // Get products
        $products = $this->service->listProducts();

        // Taux de change dynamique
        $tauxChangeEur = 655.957;
        try {
            $stmt = Database::getConnection()->query("SELECT setting_value FROM company_settings WHERE setting_key = 'taux_change_eur' LIMIT 1");
            if ($stmt) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && is_numeric($row['setting_value'])) {
                    $tauxChangeEur = (float) $row['setting_value'];
                }
            }
        } catch (\Exception $e) {}

        $pageTitle = $trajet !== null
            ? 'Enregistrer un Colis — ' . $trajet['code'] . ' (' . $trajet['libelle'] . ')'
            : 'Enregistrer un Colis';
        $activeModule = $trajet !== null
            ? 'op_' . strtolower(str_replace('-', '_', $trajet['code']))
            : 'operations';

        $this->colisageView('colisage/parcels/create', $pageTitle, $activeModule, [
            'sites' => $sites,
            'clients' => $clients,
            'products' => $products,
            'tauxChangeEur' => $tauxChangeEur,
            'trajet' => $trajet,
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();

        $trajetCodePosted = strtoupper(trim((string) ($_POST['trajet_code'] ?? '')));

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url($trajetCodePosted !== '' ? 'operation/' . $trajetCodePosted . '/saisir' : 'colisage/parcels/nouveau'));
            exit;
        }

        // Trajet Opération verrouillé : re-validation serveur obligatoire, l'agent ne
        // choisit jamais le trajet lui-même (Règle 3.4). On ne fait jamais confiance
        // au seul champ caché du formulaire.
        $trajet = null;
        if ($trajetCodePosted !== '') {
            $stmt = Database::getConnection()->prepare("SELECT * FROM trajets WHERE code = :code AND actif = 1 LIMIT 1");
            $stmt->execute(['code' => $trajetCodePosted]);
            $trajet = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            if ($trajet === null) {
                Session::flash('error', "Trajet invalide ou introuvable : {$trajetCodePosted}");
                header('Location: ' . View::url('colisage/parcels'));
                exit;
            }
        }

        // Check if quick creating shipper or consignee
        $expediteurId = (int) ($_POST['expediteur_id'] ?? 0);
        if ($expediteurId === 0 && !empty($_POST['expediteur_name'])) {
            $expediteurId = $this->service->registerClient([
                'name' => $_POST['expediteur_name'],
                'phone' => $_POST['expediteur_phone'] ?? null,
                'email' => $_POST['expediteur_email'] ?? null,
                'address' => $_POST['expediteur_address'] ?? null,
                'type' => $_POST['expediteur_type'] ?? 'standard',
            ]);
        }

        $destinataireId = (int) ($_POST['destinataire_id'] ?? 0);
        if ($destinataireId === 0 && !empty($_POST['destinataire_name'])) {
            $destinataireId = $this->service->registerClient([
                'name' => $_POST['destinataire_name'],
                'phone' => $_POST['destinataire_phone'] ?? null,
                'email' => $_POST['destinataire_email'] ?? null,
                'address' => $_POST['destinataire_address'] ?? null,
                'type' => $_POST['destinataire_type'] ?? 'standard',
            ]);
        }

        if ($expediteurId <= 0 || $destinataireId <= 0) {
            Session::flash('error', 'Veuillez sélectionner ou renseigner les informations complètes de l\'expéditeur et du destinataire.');
            header('Location: ' . View::url($trajetCodePosted !== '' ? 'operation/' . $trajetCodePosted . '/saisir' : 'colisage/parcels/nouveau'));
            exit;
        }

        $marchandises = [];
        $maxRows = max(
            count($_POST['m_custom_name'] ?? []),
            count($_POST['m_weight'] ?? []),
            count($_POST['m_nbre_colis'] ?? []),
            50
        );

        for ($idx = 0; $idx < $maxRows; $idx++) {
            $prodIds = $_POST['m_product_id_' . $idx] ?? [];
            if (!is_array($prodIds)) {
                $prodIds = [$prodIds];
            }
            if (empty($prodIds) && !empty($_POST['m_product_id'][$idx])) {
                $prodIds = [$_POST['m_product_id'][$idx]];
            }
            $prodIds = array_filter($prodIds);
            
            $customName = trim((string) ($_POST['m_custom_name'][$idx] ?? ''));
            $weight = (float) ($_POST['m_weight'][$idx] ?? 0.0);
            $prixKg = (float) ($_POST['m_prix_kg'][$idx] ?? 0.0);
            $hasEmballageSpecific = ($emballage !== '' && $emballage !== 'Propre emballage client / Aucun') || ((float)($_POST['m_prix_emballage'][$idx] ?? 0.0) > 0);

            if (!empty($prodIds) || $customName !== '' || $weight > 0 || $prixKg > 0 || $hasEmballageSpecific) {
                $marchandises[] = [
                    'product_id' => !empty($prodIds) ? (int) reset($prodIds) : null,
                    'product_ids' => $prodIds,
                    'custom_name' => $customName,
                    'custom_price' => !empty($_POST['m_custom_price'][$idx]) ? (float) $_POST['m_custom_price'][$idx] : 0.0,
                    'quantite' => (int) ($_POST['m_qty'][$idx] ?? 1),
                    'nbre_colis' => $nbreColis > 0 ? $nbreColis : 1,
                    'emballage' => $emballage !== '' ? $emballage : null,
                    'qte_emballage' => (int) ($_POST['m_qte_emballage'][$idx] ?? 1),
                    'prix_emballage' => (float) ($_POST['m_prix_emballage'][$idx] ?? 0.0),
                    'poids_unitaire' => $weight,
                    'prix_kg' => $prixKg,
                ];
            }
        }

        // Le nombre total de colis doit refléter la somme des lignes de marchandises saisies,
        // pas le champ 'nombre_colis' du formulaire (absent de cet écran, ou pas fiable côté client).
        $totalNombreColis = array_sum(array_column($marchandises, 'nbre_colis'));

        $coutAchatDhl = isset($_POST['cout_achat_dhl']) && $_POST['cout_achat_dhl'] !== '' ? (float) $_POST['cout_achat_dhl'] : 0.0;
        $awbDhl = isset($_POST['awb_dhl']) && trim($_POST['awb_dhl']) !== '' ? trim((string) $_POST['awb_dhl']) : null;

        $registerData = [
            'expediteur_id' => $expediteurId,
            'destinataire_id' => $destinataireId,
            'poids_total' => (float) ($_POST['poids_total'] ?? 0.0),
            'nombre_colis' => $totalNombreColis > 0 ? $totalNombreColis : (int) ($_POST['nombre_colis'] ?? 1),
            'valeur_declaree' => (float) ($_POST['valeur_declaree'] ?? 0.0),
            'montant_total' => (float) ($_POST['valeur_declaree'] ?? 0.0),
            'devise' => $_POST['devise'] ?? 'XOF',
            'agence_depart_id' => !empty($_POST['agence_depart_id']) ? (int) $_POST['agence_depart_id'] : null,
            'agence_arrivee_id' => !empty($_POST['agence_arrivee_id']) ? (int) $_POST['agence_arrivee_id'] : null,
            'type_expediteur' => $_POST['type_expediteur'] ?? 'export_aerien',
            'assurance_souscrite' => !empty($_POST['assurance_souscrite']) ? 1 : 0,
            'date_depart_prevue' => !empty($_POST['date_depart_prevue']) ? $_POST['date_depart_prevue'] : (!empty($_POST['date_enregistrement']) ? $_POST['date_enregistrement'] : date('Y-m-d')),
            'created_at' => !empty($_POST['date_enregistrement']) ? $_POST['date_enregistrement'] . ' ' . date('H:i:s') : (!empty($_POST['date_depart_prevue']) ? $_POST['date_depart_prevue'] . ' ' . date('H:i:s') : date('Y-m-d H:i:s')),
            'marchandises' => $marchandises,
            'created_by' => Auth::id(),
            'awb_dhl' => $awbDhl,
            'cout_achat_dhl' => $coutAchatDhl,
        ];

        if ($trajet !== null) {
            // Le trajet verrouillé impose son propre type de transport et son libellé de trafic ;
            // l'agent ne peut ni le choisir ni le modifier depuis ce formulaire.
            $registerData['type_expediteur'] = $trajet['type_transport'];
            $registerData['trafic'] = $trajet['code'] . ' - ' . $trajet['libelle'];
            $registerData['trajet_code_locked'] = $trajet['code'];
        }

        $newId = $this->service->registerParcel($registerData);

        $auditId = AuditLogService::log('create_parcel', 'lbp_colis', $newId, null, $registerData);

        // Règle anti-fraude : sous-déclaration de valeur/poids
        IntegrityRuleEngine::evaluateSousDeclarationColis(
            (int) Auth::id(),
            $newId,
            (float) ($registerData['valeur_declaree'] ?? 0),
            (float) ($registerData['poids_total'] ?? 0),
            $auditId
        );

        if ($trajet !== null) {
            $userId = Auth::id();
            $upStmt = Database::getConnection()->prepare("
                UPDATE lbp_colis
                SET trajet_id = :trajet_id,
                    trajet = :trajet_code,
                    agent_groupage_id = COALESCE(agent_groupage_id, :agent_id)
                WHERE id = :id
            ");
            $upStmt->execute([
                'trajet_id' => $trajet['id'],
                'trajet_code' => $trajet['code'],
                'agent_id' => $userId,
                'id' => $newId,
            ]);
        }

        header('Location: ' . View::url('colisage/parcels/' . $newId));
        exit;
    }

    public function autoFacturer(int $id): void
    {
        AuthMiddleware::check();
        $db = Database::getConnection();
        $factureRepo = new \App\Repositories\Finance\FactureRepository($db);
        try {
            $invoiceId = $factureRepo->createAutoInvoiceFromParcel($id, (int) Auth::id());
            AuditLogService::log('auto_facturer_colis', 'lbp_factures', $invoiceId, null, ['colis_id' => $id]);
            Session::flash('success', 'Facture générée avec succès en 1 Clic !');
            header('Location: ' . View::url('finance/factures/' . $invoiceId));
            exit;
        } catch (\Exception $e) {
            Session::flash('error', 'Erreur lors de la génération de la facture : ' . $e->getMessage());
            header('Location: ' . View::url('colisage/parcels/' . $id));
            exit;
        }
    }

    public function editParcel(int $id): void
    {
        AuthMiddleware::check();

        if ((!Auth::isAdmin() && !Auth::hasRole('dg')) || Auth::isAssistantDg()) {
            Session::flash('error', "Seuls l'Administrateur et le Directeur Général ont le droit de modifier un colis.");
            header('Location: ' . View::url('colisage/parcels/' . $id));
            exit;
        }

        $colis = $this->service->getParcelDetails($id);
        if ($colis === null) {
            Session::flash('error', 'Colis introuvable.');
            header('Location: ' . View::url('colisage/parcels'));
            exit;
        }

        $clients = $this->service->listClients();
        $products = $this->service->listProducts();
        $trajets = [];

        $this->colisageView('colisage/parcels/edit', 'Modifier le Colis ' . $colis['numero_tracking'], 'operations', [
            'colis' => $colis,
            'clients' => $clients,
            'products' => $products,
            'trajets' => $trajets,
        ]);
    }

    public function updateParcel(int $id): void
    {
        AuthMiddleware::check();

        if ((!Auth::isAdmin() && !Auth::hasRole('dg')) || Auth::isAssistantDg()) {
            Session::flash('error', "Seuls l'Administrateur et le Directeur Général ont le droit de modifier un colis.");
            header('Location: ' . View::url('colisage/parcels/' . $id));
            exit;
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('colisage/parcels/' . $id . '/modifier'));
            exit;
        }

        $expediteurId = (int) ($_POST['expediteur_id'] ?? 0);
        $destinataireId = (int) ($_POST['destinataire_id'] ?? 0);

        if ($expediteurId <= 0 || $destinataireId <= 0) {
            Session::flash('error', 'Veuillez sélectionner des clients valides pour l\'expéditeur et le destinataire.');
            header('Location: ' . View::url('colisage/parcels/' . $id . '/modifier'));
            exit;
        }

        $marchandises = [];
        $maxRows = max(
            count($_POST['m_custom_name'] ?? []),
            count($_POST['m_weight'] ?? []),
            count($_POST['m_nbre_colis'] ?? []),
            50
        );

        for ($idx = 0; $idx < $maxRows; $idx++) {
            $prodIds = $_POST['m_product_id_' . $idx] ?? [];
            if (!is_array($prodIds)) {
                $prodIds = [$prodIds];
            }
            if (empty($prodIds) && !empty($_POST['m_product_id'][$idx])) {
                $prodIds = [$_POST['m_product_id'][$idx]];
            }
            $prodIds = array_filter($prodIds);
            
            $customName = trim((string) ($_POST['m_custom_name'][$idx] ?? ''));
            $weight = (float) ($_POST['m_weight'][$idx] ?? 0.0);
            $prixKg = (float) ($_POST['m_prix_kg'][$idx] ?? 0.0);
            $emballage = trim((string) ($_POST['m_emballage'][$idx] ?? ''));
            $nbreColis = (int) ($_POST['m_nbre_colis'][$idx] ?? 1);
            $hasEmballageSpecific = ($emballage !== '' && $emballage !== 'Propre emballage client / Aucun') || ((float)($_POST['m_prix_emballage'][$idx] ?? 0.0) > 0);

            if (!empty($prodIds) || $customName !== '' || $weight > 0 || $prixKg > 0 || $hasEmballageSpecific) {
                $marchandises[] = [
                    'product_id' => !empty($prodIds) ? (int) reset($prodIds) : null,
                    'product_ids' => $prodIds,
                    'custom_name' => $customName,
                    'custom_price' => !empty($_POST['m_custom_price'][$idx]) ? (float) $_POST['m_custom_price'][$idx] : 0.0,
                    'quantite' => (int) ($_POST['m_qty'][$idx] ?? 1),
                    'nbre_colis' => $nbreColis > 0 ? $nbreColis : 1,
                    'emballage' => $emballage !== '' ? $emballage : null,
                    'qte_emballage' => (int) ($_POST['m_qte_emballage'][$idx] ?? 1),
                    'prix_emballage' => (float) ($_POST['m_prix_emballage'][$idx] ?? 0.0),
                    'poids_unitaire' => $weight,
                    'prix_kg' => $prixKg,
                ];
            }
        }

        $totalNombreColis = array_sum(array_column($marchandises, 'nbre_colis'));

        $coutAchatDhl = isset($_POST['cout_achat_dhl']) && $_POST['cout_achat_dhl'] !== '' ? (float) $_POST['cout_achat_dhl'] : 0.0;
        $awbDhl = isset($_POST['awb_dhl']) && trim($_POST['awb_dhl']) !== '' ? trim((string) $_POST['awb_dhl']) : null;

        $updateData = [
            'expediteur_id' => $expediteurId,
            'destinataire_id' => $destinataireId,
            'poids_total' => (float) ($_POST['poids_total'] ?? 0.0),
            'nombre_colis' => $totalNombreColis > 0 ? $totalNombreColis : (int) ($_POST['nombre_colis'] ?? 1),
            'valeur_declaree' => (float) ($_POST['valeur_declaree'] ?? 0.0),
            'statut' => $_POST['statut'] ?? 'enregistre',
            'assurance_souscrite' => !empty($_POST['assurance_souscrite']) ? 1 : 0,
            'montant_assurance' => (float) ($_POST['montant_assurance'] ?? 0.0),
            'date_depart_prevue' => $_POST['date_depart_prevue'] ?? date('Y-m-d'),
            'marchandises' => $marchandises,
            'awb_dhl' => $awbDhl,
            'cout_achat_dhl' => $coutAchatDhl,
        ];

        try {
            $this->service->updateParcel($id, $updateData);
            AuditLogService::log('update_colis', 'lbp_colis', $id, null, ['user_id' => Auth::id()]);
            Session::flash('success', 'Colis mis à jour avec succès par la Direction.');
        } catch (\Exception $e) {
            Session::flash('error', 'Erreur lors de la modification du colis : ' . $e->getMessage());
        }

        header('Location: ' . View::url('colisage/parcels/' . $id));
        exit;
    }

    public function show(int $id): void
    {
        AuthMiddleware::check();

        $colis = $this->service->getParcelDetails($id);
        if ($colis === null) {
            header('Location: ' . View::url('colisage/parcels'));
            exit;
        }

        $this->colisageView('colisage/parcels/show', 'Détails du Colis ' . $colis['numero_tracking'], 'operations', [
            'colis' => $colis,
        ]);
    }

    public function printInvoice(int $id): void
    {
        AuthMiddleware::check();

        $colis = $this->service->getParcelDetails($id);
        if ($colis === null) {
            header('Location: ' . View::url('colisage/parcels'));
            exit;
        }

        // We load this without the base module layout so it's clean and printable
        require BASE_PATH . '/views/colisage/parcels/facture.php';
    }

    public function printLabel(int $id): void
    {
        AuthMiddleware::check();

        $colis = $this->service->getParcelDetails($id);
        if ($colis === null) {
            header('Location: ' . View::url('colisage/parcels'));
            exit;
        }

        // Retrieve assigned Rayon code if present
        if (!empty($colis['rayon_id'])) {
            $pdo = \App\Models\Database::getConnection();
            $stmt = $pdo->prepare("SELECT code_rayon, nom_rayon FROM logistique_rayons WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $colis['rayon_id']]);
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($r) {
                $colis['code_rayon'] = $r['code_rayon'];
                $colis['nom_rayon'] = $r['nom_rayon'];
            }
        }

        require BASE_PATH . '/views/colisage/parcels/etiquette.php';
    }

    public function withdraw(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('colisage/parcels/' . $id));
            exit;
        }

        $this->service->withdrawParcel($id, [
            'recup_nom' => $_POST['recup_nom'] ?? '',
            'recup_cni' => $_POST['recup_cni'] ?? '',
            'recup_telephone' => $_POST['recup_telephone'] ?? '',
        ]);

        header('Location: ' . View::url('colisage/parcels/' . $id));
        exit;
    }

    public function transfer(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('colisage/parcels/' . $id));
            exit;
        }

        $newRayonId = (int) ($_POST['rayon_id'] ?? 0);
        $commentaires = trim((string) ($_POST['commentaires'] ?? ''));

        if ($newRayonId > 0) {
            $this->service->transferParcelToRayon($id, $newRayonId, $commentaires);
            Session::flash('success', 'Le colis a été réaffecté / transféré dans le nouveau rayon avec succès.');
        }

        header('Location: ' . View::url('colisage/parcels/' . $id));
        exit;
    }

    /**
     * Suppression physique d'un colis — réservée Admin/DG. Un colis déjà facturé ne peut
     * pas être supprimé (contrainte RESTRICT en base) : la facture doit d'abord être annulée
     * via l'écran Facturation, le modèle de verrouillage/audit n'autorisant pas sa suppression.
     */
    public function deleteParcel(int $id): void
    {
        RoleMiddleware::check(['chef_agence', 'caissiere_principale', 'assistant_dg', 'dg']);

        if (Auth::isAssistantDg()) {
            Session::flash('error', "Action non autorisée : L'Assistant DG dispose de la consultation globale mais ne peut pas effectuer de suppressions.");
            header('Location: ' . View::url('colisage/parcels'));
            exit;
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('colisage/parcels'));
            exit;
        }

        try {
            $pdo = \App\Models\Database::getConnection();

            // Supprimer la facture associée si elle existe et est impayée (ou si DG/Admin)
            $stmtFacture = $pdo->prepare("SELECT id, numero_facture, statut FROM lbp_factures WHERE colis_id = :colis_id LIMIT 1");
            $stmtFacture->execute(['colis_id' => $id]);
            $facture = $stmtFacture->fetch(\PDO::FETCH_ASSOC);

            if ($facture) {
                if (in_array($facture['statut'], ['emise', 'brouillon', 'annulee']) || Auth::hasRole('dg') || Auth::isAdmin()) {
                    $pdo->prepare("DELETE FROM lbp_factures WHERE id = :id")->execute(['id' => $facture['id']]);
                    AuditLogService::log('delete_invoice_auto', 'lbp_factures', (int) $facture['id'], $facture, null);
                } else {
                    Session::flash('error', "La facture associée (" . $facture['numero_facture'] . ") est déjà entièrement ou partiellement payée. Seul le DG peut la supprimer.");
                    header('Location: ' . View::url('colisage/parcels'));
                    exit;
                }
            }

            $colisAvantSuppression = $this->service->getParcelDetails($id);
            $this->service->deleteParcel($id);
            $auditId = AuditLogService::log('delete_parcel', 'lbp_colis', $id, $colisAvantSuppression, null);

            // Reséquençage automatique des colis suivants
            if ($colisAvantSuppression && !empty($colisAvantSuppression['numero_tracking'])) {
                $tracking = $colisAvantSuppression['numero_tracking'];
                $parts = explode('-', $tracking);
                if (count($parts) >= 2) {
                    $sequenceStr = array_pop($parts);
                    $prefix = implode('-', $parts);
                    $sequence = (int) $sequenceStr;

                    if ($sequence > 0) {
                        $stmtC = $pdo->prepare("SELECT id, numero_tracking FROM lbp_colis WHERE numero_tracking LIKE :prefix ORDER BY id ASC");
                        $stmtC->execute(['prefix' => $prefix . '-%']);
                        $colisList = $stmtC->fetchAll(\PDO::FETCH_ASSOC);

                        foreach ($colisList as $c) {
                            $cParts = explode('-', $c['numero_tracking']);
                            $cSeqStr = array_pop($cParts);
                            $cSeq = (int) $cSeqStr;
                            if ($cSeq > $sequence) {
                                $newSeq = $cSeq - 1;
                                $paddingLen = strlen($cSeqStr);
                                $newSeqStr = str_pad((string)$newSeq, $paddingLen, '0', STR_PAD_LEFT);
                                $newTracking = implode('-', $cParts) . '-' . $newSeqStr;

                                $upStmt = $pdo->prepare("UPDATE lbp_colis SET numero_tracking = :new_track WHERE id = :id");
                                $upStmt->execute(['new_track' => $newTracking, 'id' => $c['id']]);
                            }
                        }
                    }
                }
            }

            // Reséquençage automatique des factures suivantes
            if ($facture && !empty($facture['numero_facture'])) {
                $numFacture = $facture['numero_facture'];
                $fParts = explode('-', $numFacture);
                if (count($fParts) >= 4) {
                    $fSeqStr = array_pop($fParts);
                    $fPrefix = implode('-', $fParts);
                    $fSequence = (int) $fSeqStr;

                    if ($fSequence > 0) {
                        $stmtF = $pdo->prepare("SELECT id, numero_facture FROM lbp_factures WHERE numero_facture LIKE :prefix ORDER BY id ASC");
                        $stmtF->execute(['prefix' => $fPrefix . '-%']);
                        $facturesList = $stmtF->fetchAll(\PDO::FETCH_ASSOC);

                        foreach ($facturesList as $f) {
                            $cfParts = explode('-', $f['numero_facture']);
                            $cfSeqStr = array_pop($cfParts);
                            $cfSeq = (int) $cfSeqStr;
                            if ($cfSeq > $fSequence) {
                                $newFSeq = $cfSeq - 1;
                                $fPaddingLen = strlen($cfSeqStr);
                                $newFSeqStr = str_pad((string)$newFSeq, $fPaddingLen, '0', STR_PAD_LEFT);
                                $newFactureNum = implode('-', $cfParts) . '-' . $newFSeqStr;

                                $upFStmt = $pdo->prepare("UPDATE lbp_factures SET numero_facture = :new_num WHERE id = :id");
                                $upFStmt->execute(['new_num' => $newFactureNum, 'id' => $f['id']]);
                            }
                        }
                    }
                }
            }

            // Règle anti-fraude : suppression en dehors des heures de bureau
            IntegrityRuleEngine::evaluateSuppressionHorsHoraires(
                (int) Auth::id(), 'lbp_colis', $id, $auditId
            );
            Session::flash('success', 'Le colis et sa facture associée ont été supprimés avec succès.');
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Session::flash('error', "Ce colis ne peut pas être supprimé car d'autres enregistrements y sont liés.");
            } else {
                Session::flash('error', 'Erreur lors de la suppression du colis : ' . $e->getMessage());
            }
        }

        header('Location: ' . View::url('colisage/parcels'));
        exit;
    }

    public function updateStatutDepart(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Token CSRF invalide.']);
                exit;
            }
            Session::flash('error', 'Session expirée.');
            header('Location: ' . View::url('colisage/parcels/' . $id));
            exit;
        }

        $statutDepart = trim((string) ($_POST['statut_depart'] ?? 'NON_SPECIFIE'));
        $motifReste = trim((string) ($_POST['motif_reste'] ?? ''));

        if (!in_array($statutDepart, ['NON_SPECIFIE', 'PARTI', 'RESTE'], true)) {
            $statutDepart = 'NON_SPECIFIE';
        }

        $this->service->updateParcelStatutDepart($id, $statutDepart, $motifReste);

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'statut_depart' => $statutDepart, 'motif_reste' => $motifReste]);
            exit;
        }

        Session::flash('success', 'Statut de départ du colis mis à jour avec succès.');
        $redirectUrl = $_POST['redirect_url'] ?? View::url('colisage/parcels/' . $id);
        header('Location: ' . $redirectUrl);
        exit;
    }

    // ==========================================
    // GROUPAGE / MANIFESTES
    // ==========================================

    public function groupageIndex(): void
    {
        AuthMiddleware::check();

        $expeditions = $this->service->listExpeditions();

        $this->colisageView('colisage/groupage/index', 'Groupage & Manifestes', 'groupage', [
            'expeditions' => $expeditions,
        ]);
    }

    public function groupageCreate(): void
    {
        AuthMiddleware::check();

        $sitesStmt = Database::getConnection()->query("SELECT id, name FROM company_sites WHERE is_active = 1");
        $sites = $sitesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->colisageView('colisage/groupage/create', 'Planifier une Expédition (Groupage)', 'groupage', [
            'sites' => $sites,
        ]);
    }

    public function groupageStore(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('colisage/groupage/nouveau'));
            exit;
        }

        $id = $this->service->createExpedition([
            'type_transport' => $_POST['type_transport'] ?? 'AÉRIEN',
            'agence_depart_id' => (int) ($_POST['agence_depart_id'] ?? 0),
            'agence_arrivee_id' => (int) ($_POST['agence_arrivee_id'] ?? 0),
            'date_depart_prevue' => $_POST['date_depart_prevue'] ?? null,
            'date_arrivee_estimee' => $_POST['date_arrivee_estimee'] ?? null,
        ]);

        header('Location: ' . View::url('colisage/groupage/' . $id));
        exit;
    }

    public function groupageShow(int $id): void
    {
        AuthMiddleware::check();

        $exp = $this->service->getExpeditionDetails($id);
        if ($exp === null) {
            header('Location: ' . View::url('colisage/groupage'));
            exit;
        }

        $availableParcels = $this->service->getParcelsAvailableForGroupage((int) $exp['agence_depart_id']);

        $this->colisageView('colisage/groupage/show', 'Manifeste ' . $exp['reference'], 'groupage', [
            'exp' => $exp,
            'availableParcels' => $availableParcels,
        ]);
    }

    public function groupagePrintManifest(int $id): void
    {
        AuthMiddleware::check();

        $exp = $this->service->getExpeditionDetails($id);
        if ($exp === null) {
            header('Location: ' . View::url('colisage/groupage'));
            exit;
        }

        // Fetch merchandise details for each parcel in the expedition
        if (!empty($exp['parcels']) && is_array($exp['parcels'])) {
            foreach ($exp['parcels'] as &$p) {
                $p['marchandises'] = $this->service->getParcelDetails((int) $p['id'])['marchandises'] ?? [];
            }
            unset($p);
        }

        require BASE_PATH . '/views/colisage/groupage/manifeste.php';
    }

    public function groupageAddParcel(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('colisage/groupage/' . $id));
            exit;
        }

        $parcelId = (int) ($_POST['colis_id'] ?? 0);
        if ($parcelId > 0) {
            $this->service->addParcelToExpedition($parcelId, $id);
        }

        header('Location: ' . View::url('colisage/groupage/' . $id));
        exit;
    }

    public function groupageStart(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('colisage/groupage/' . $id));
            exit;
        }

        $this->service->startExpedition($id);

        header('Location: ' . View::url('colisage/groupage/' . $id));
        exit;
    }

    public function groupageArrive(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('colisage/groupage/' . $id));
            exit;
        }

        $this->service->arriveExpedition($id);

        header('Location: ' . View::url('colisage/groupage/' . $id));
        exit;
    }

    public function documents(): void
    {
        AuthMiddleware::check();
        $pdo = Database::getConnection();

        // Fetch recent manifestes (groupages)
        $manifestsStmt = $pdo->query("
            SELECT e.*, 
                   s_dep.name AS agence_depart_name,
                   s_arr.name AS agence_arrivee_name,
                   (SELECT COUNT(*) FROM lbp_colis WHERE expedition_id = e.id) as colis_count
            FROM lbp_expeditions e
            JOIN company_sites s_dep ON e.agence_depart_id = s_dep.id
            JOIN company_sites s_arr ON e.agence_arrivee_id = s_arr.id
            ORDER BY e.created_at DESC
            LIMIT 20
        ");
        $manifests = $manifestsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Fetch recent parcels to print invoices or shipping labels
        $parcelsStmt = $pdo->query("
            SELECT c.*, cli_exp.name AS expediteur_name, cli_dest.name AS destinataire_name
            FROM lbp_colis c
            JOIN lbp_clients cli_exp ON c.expediteur_id = cli_exp.id
            JOIN lbp_clients cli_dest ON c.destinataire_id = cli_dest.id
            ORDER BY c.created_at DESC
            LIMIT 30
        ");
        $parcels = $parcelsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->colisageView('colisage/documents', 'Bordereaux, Factures & Documents', 'documents', [
            'manifests' => $manifests,
            'parcels' => $parcels,
        ]);
    }

    public function reporting(): void
    {
        AuthMiddleware::check();
        $pdo = Database::getConnection();

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) $dateDebut = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) $dateFin = date('Y-m-d');

        // 1. Tonnage total et volume par trajet
        $tonnageStmt = $pdo->prepare("
            SELECT trajet, 
                   SUM(poids_total) as total_poids,
                   COUNT(id) as total_colis
            FROM lbp_colis
            WHERE DATE(created_at) >= :date_debut AND DATE(created_at) <= :date_fin
            GROUP BY trajet
        ");
        $tonnageStmt->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
        $tonnageData = $tonnageStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // 2. Chiffre d'affaires par mode
        $caStmt = $pdo->prepare("
            SELECT type_expediteur, 
                   SUM(montant_total) as total_ca,
                   devise
            FROM lbp_colis
            WHERE DATE(created_at) >= :date_debut AND DATE(created_at) <= :date_fin
            GROUP BY type_expediteur, devise
        ");
        $caStmt->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
        $caData = $caStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // 3. Delais moyens logistiques
        $delaiStmt = $pdo->prepare("
            SELECT agence_depart_id, agence_arrivee_id, 
                   AVG(DATEDIFF(recup_date_heure, created_at)) as avg_days
            FROM lbp_colis
            WHERE statut IN ('LIVRÉ', 'RETIRÉ') 
              AND recup_date_heure IS NOT NULL
              AND DATE(created_at) >= :date_debut AND DATE(created_at) <= :date_fin
            GROUP BY agence_depart_id, agence_arrivee_id
        ");
        $delaiStmt->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
        $delaiData = $delaiStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->colisageView('colisage/reporting', 'Statistiques & Performance Fret', 'reporting', [
            'tonnageData' => $tonnageData,
            'caData' => $caData,
            'delaiData' => $delaiData,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
        ]);
    }

    // ==========================================
    // PARAMÉTRAGE / SETTINGS
    // ==========================================

    public function settings(): void
    {
        AuthMiddleware::check();
        $pdo = Database::getConnection();

        // Taux de change depuis company_settings
        $tauxChangeEur = 655.957;
        try {
            $stmt = $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'taux_change_eur' LIMIT 1");
            if ($stmt) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && is_numeric($row['setting_value'])) {
                    $tauxChangeEur = (float) $row['setting_value'];
                }
            }
        } catch (\Exception $e) {}

        // Taux depuis lbp_devises_taux
        $devisesRates = [];
        try {
            $stmt = $pdo->query("SELECT * FROM lbp_devises_taux ORDER BY id ASC");
            $devisesRates = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Tous les settings colisage
        $allSettings = [];
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value, updated_at FROM company_settings WHERE setting_key LIKE 'colisage_%' OR setting_key LIKE 'taux_%'");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $allSettings[$r['setting_key']] = $r['setting_value'];
                if ($r['setting_key'] === 'taux_change_eur') {
                    $allSettings['taux_change_eur_updated'] = date('d/m/Y à H:i', strtotime($r['updated_at']));
                }
            }
        } catch (\Exception $e) {}

        $this->colisageView('colisage/settings', 'Paramétrage Colisage', 'settings', [
            'tauxChangeEur' => $tauxChangeEur,
            'devisesRates' => $devisesRates,
            'allSettings' => $allSettings,
        ]);
    }

    public function saveSettings(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['admin', 'chef_agence']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('colisage/settings'));
            exit;
        }

        $pdo = Database::getConnection();

        $section = $_POST['section'] ?? '';

        if ($section === 'taux_change') {
            $taux = (float) ($_POST['taux_change_eur'] ?? 655.957);
            if ($taux > 0) {
                // Upsert dans company_settings
                $stmt = $pdo->prepare("
                    INSERT INTO company_settings (setting_key, setting_value, setting_label)
                    VALUES ('taux_change_eur', :val, 'Taux de change EUR/XOF')
                    ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = NOW()
                ");
                $stmt->execute(['val' => (string) $taux, 'val2' => (string) $taux]);

                // Synchroniser aussi dans lbp_devises_taux
                try {
                    $stmt = $pdo->prepare("UPDATE lbp_devises_taux SET taux = :taux WHERE devise_source = 'EUR' AND devise_cible = 'XOF'");
                    $stmt->execute(['taux' => $taux]);
                    $inverse = round(1 / $taux, 6);
                    $stmt = $pdo->prepare("UPDATE lbp_devises_taux SET taux = :taux WHERE devise_source = 'XOF' AND devise_cible = 'EUR'");
                    $stmt->execute(['taux' => $inverse]);
                } catch (\Exception $e) {}

                \App\Helpers\Session::flash('success', 'Le taux de change EUR/XOF a été mis à jour : ' . number_format($taux, 6, ',', '.') . ' FCFA.');
            }
        } elseif ($section === 'preferences') {
            $keys = [
                'colisage_tracking_prefix',
                'colisage_default_devise',
                'colisage_sla_jours',
                'colisage_tel_service_client',
            ];
            foreach ($keys as $key) {
                $value = trim((string) ($_POST[$key] ?? ''));
                if ($value !== '') {
                    $stmt = $pdo->prepare("
                        INSERT INTO company_settings (setting_key, setting_value, setting_label)
                        VALUES (:key, :val, :label)
                        ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = NOW()
                    ");
                    $label = str_replace('_', ' ', ucfirst(str_replace('colisage_', '', $key)));
                    $stmt->execute(['key' => $key, 'val' => $value, 'val2' => $value, 'label' => $label]);
                }
            }
            \App\Helpers\Session::flash('success', 'Les préférences opérationnelles ont été mises à jour.');
        }

        $this->redirect('colisage/settings');
    }

    public function expressScanPage(): void
    {
        AuthMiddleware::check();
        $this->colisageView('colisage/parcels/scan_express', 'Scan Express Douchette & Tracking 2-Scans', 'operations', []);
    }

    public function processExpressScan(): void
    {
        AuthMiddleware::check();
        header('Content-Type: application/json; charset=utf-8');

        $barcode = trim((string) ($_POST['barcode'] ?? $_GET['barcode'] ?? ''));
        $action = strtoupper(trim((string) ($_POST['scan_action'] ?? $_GET['scan_action'] ?? 'DEPART')));

        if ($barcode === '') {
            echo json_encode(['success' => false, 'message' => 'Veuillez biper / scanner un code-barres valide.']);
            exit;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*, s_dep.name AS agence_depart_name, s_arr.name AS agence_arrivee_name
            FROM lbp_colis c
            LEFT JOIN company_sites s_dep ON c.agence_depart_id = s_dep.id
            LEFT JOIN company_sites s_arr ON c.agence_arrivee_id = s_arr.id
            WHERE c.numero_tracking = :code OR c.id = :id_code
            LIMIT 1
        ");
        $stmt->execute(['code' => $barcode, 'id_code' => is_numeric($barcode) ? (int)$barcode : 0]);
        $colis = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$colis) {
            echo json_encode(['success' => false, 'message' => "Colis introuvable avec le code-barres '{$barcode}'."]);
            exit;
        }

        $colisId = (int) $colis['id'];

        if ($action === 'DEPART') {
            $updateStmt = $pdo->prepare("
                UPDATE lbp_colis 
                SET statut = 'EN_TRANSIT', statut_depart = 'PARTI', updated_at = NOW() 
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $colisId]);

            $etapeText = 'Départ de l\'agence ' . ($colis['agence_depart_name'] ?? 'de départ') . ' (Scan Douchette au départ)';
            $gpsStmt = $pdo->prepare("
                INSERT INTO lbp_tracking_gps (colis_id, etape, date_etape)
                VALUES (:colis_id, :etape, NOW())
            ");
            $gpsStmt->execute(['colis_id' => $colisId, 'etape' => $etapeText]);

            echo json_encode([
                'success' => true,
                'action' => 'DEPART',
                'statut' => 'EN_TRANSIT',
                'tracking' => $colis['numero_tracking'],
                'message' => "Colis {$colis['numero_tracking']} marqué EN TRANSIT au départ. Le tracking dynamique est démarré !",
            ]);
            exit;
        } else {
            // ARRIVEE
            $updateStmt = $pdo->prepare("
                UPDATE lbp_colis 
                SET statut = 'ARRIVÉ', statut_arrive = 'ARRIVE', updated_at = NOW() 
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $colisId]);

            $etapeText = 'Arrivée réceptionnée à l\'agence ' . ($colis['agence_arrivee_name'] ?? 'de destination') . ' (Scan Douchette à l\'arrivée)';
            $gpsStmt = $pdo->prepare("
                INSERT INTO lbp_tracking_gps (colis_id, etape, date_etape)
                VALUES (:colis_id, :etape, NOW())
            ");
            $gpsStmt->execute(['colis_id' => $colisId, 'etape' => $etapeText]);

            try {
                $notifRepo = new \App\Repositories\Shared\NotificationRepository($pdo);
                $notifService = new \App\Services\Shared\NotificationService($notifRepo);
                $colisRepo = new ColisageRepository($pdo);
                $pDetails = $colisRepo->findParcelById($colisId);
                if ($pDetails) {
                    $notifService->notifyParcelStatusChange($pDetails, 'ARRIVÉ', $etapeText);
                }
            } catch (\Throwable $e) {}

            echo json_encode([
                'success' => true,
                'action' => 'ARRIVEE',
                'statut' => 'ARRIVÉ',
                'tracking' => $colis['numero_tracking'],
                'message' => "Colis {$colis['numero_tracking']} marqué ARRIVÉ à destination. Notification client transmise !",
            ]);
            exit;
        }
    }

    public function userGuide(): void
    {
        \App\Middleware\AuthMiddleware::check();

        $this->colisageView('colisage/guide', 'Guide d\'Utilisation de la Saisie', 'guide_utilisation');
    }
}
