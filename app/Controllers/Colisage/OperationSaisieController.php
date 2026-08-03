<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Middleware\AuthMiddleware;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Models\Database;
use App\Repositories\Colisage\ColisageRepository;
use App\Services\Colisage\ColisageService;
use App\Helpers\View;
use PDO;

final class OperationSaisieController extends ColisageBaseController
{
    private ColisageService $service;
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->service = new ColisageService(new ColisageRepository($this->pdo));
    }

    /**
     * Formulaire de saisie pour un trajet spécifique imposé.
     */
    public function create(string $code): void
    {
        AuthMiddleware::check();

        $code = strtoupper(trim($code));

        // Retrieve locked trajet definition from DB
        $stmt = $this->pdo->prepare("SELECT * FROM trajets WHERE code = :code AND actif = 1 LIMIT 1");
        $stmt->execute(['code' => $code]);
        $trajet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trajet) {
            Session::flash('error', "Le trajet spécifié ('{$code}') n'existe pas ou est inactif.");
            header('Location: ' . View::url('colisage/parcels'));
            exit;
        }

        // Get sites
        $sitesStmt = $this->pdo->query("SELECT id, name, code FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        $sites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Get clients & products
        $clients = $this->service->listClients();
        $products = $this->service->listProducts();

        // Taux de change EUR -> XOF
        $tauxChangeEur = 655.957;
        try {
            $rStmt = $this->pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'taux_change_eur' LIMIT 1");
            if ($rStmt) {
                $val = $rStmt->fetchColumn();
                if ($val && is_numeric($val)) {
                    $tauxChangeEur = (float) $val;
                }
            }
        } catch (\Throwable $e) {}

        $this->colisageView('colisage/operation/saisie', 'Saisie ' . $trajet['code'] . ' (' . $trajet['libelle'] . ')', 'operations', [
            'trajet' => $trajet,
            'sites' => $sites,
            'clients' => $clients,
            'products' => $products,
            'tauxChangeEur' => $tauxChangeEur,
        ]);
    }

    /**
     * Enregistrement de la saisie d'un colis avec trajet imposé.
     */
    public function store(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            $code = $_POST['trajet_code'] ?? 'LB-CI';
            header('Location: ' . View::url('operation/' . $code . '/saisir'));
            exit;
        }

        $trajetCode = strtoupper(trim($_POST['trajet_code'] ?? ''));

        // Retrieve target trajet
        $stmt = $this->pdo->prepare("SELECT * FROM trajets WHERE code = :code AND actif = 1 LIMIT 1");
        $stmt->execute(['code' => $trajetCode]);
        $trajet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trajet) {
            Session::flash('error', "Trajet invalide ou introuvable : {$trajetCode}");
            header('Location: ' . View::url('colisage/parcels'));
            exit;
        }

        // Quick creation shipper / consignee
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

        // Parse marchandises
        $marchandises = [];
        for ($idx = 0; $idx < 100; $idx++) {
            $prodIds = $_POST['m_product_id_' . $idx] ?? [];
            if (!is_array($prodIds)) {
                $prodIds = [$prodIds];
            }
            if (empty($prodIds) && !empty($_POST['m_product_id'][$idx])) {
                $prodIds = [$_POST['m_product_id'][$idx]];
            }
            $prodIds = array_filter($prodIds);

            $customName = $_POST['m_custom_name'][$idx] ?? '';
            if (!empty($prodIds) || !empty($customName)) {
                $marchandises[] = [
                    'product_id' => !empty($prodIds) ? (int) reset($prodIds) : null,
                    'product_ids' => $prodIds,
                    'custom_name' => $customName,
                    'custom_price' => !empty($_POST['m_custom_price'][$idx]) ? (float) $_POST['m_custom_price'][$idx] : 0.0,
                    'quantite' => (int) ($_POST['m_qty'][$idx] ?? 1),
                    'nbre_colis' => (int) ($_POST['m_nbre_colis'][$idx] ?? 1),
                    'emballage' => $_POST['m_emballage'][$idx] ?? null,
                    'qte_emballage' => (int) ($_POST['m_qte_emballage'][$idx] ?? 1),
                    'poids_unitaire' => (float) ($_POST['m_weight'][$idx] ?? 0.0),
                    'prix_kg' => (float) ($_POST['m_prix_kg'][$idx] ?? 0.0),
                ];
            }
        }

        $userId = $_SESSION['auth_user_id'] ?? ($_SESSION['user']['id'] ?? null);

        // Register parcel with LOCKED trajet_id and locked trajet details
        $newParcelId = $this->service->registerParcel([
            'expediteur_id' => $expediteurId,
            'destinataire_id' => $destinataireId,
            'poids_total' => (float) ($_POST['poids_total'] ?? 0.0),
            'nombre_colis' => (int) ($_POST['nombre_colis'] ?? 1),
            'valeur_declaree' => (float) ($_POST['valeur_declaree'] ?? 0.0),
            'montant_total' => (float) ($_POST['montant_total'] ?? $_POST['valeur_declaree'] ?? 0.0),
            'devise' => $_POST['devise'] ?? 'XOF',
            'agence_depart_id' => !empty($_POST['agence_depart_id']) ? (int) $_POST['agence_depart_id'] : null,
            'agence_arrivee_id' => !empty($_POST['agence_arrivee_id']) ? (int) $_POST['agence_arrivee_id'] : null,
            'type_expediteur' => $trajet['type_transport'],
            'trajet' => $trajet['code'],
            'trajet_id' => $trajet['id'],
            'trafic' => $trajet['code'] . ' - ' . $trajet['libelle'],
            'agent_groupage_id' => $userId,
            'marchandises' => $marchandises,
        ]);

        // Explicitly force updating trajet_id and agent_groupage_id in database
        $upStmt = $this->pdo->prepare("
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
            'id' => $newParcelId,
        ]);

        Session::flash('success', "Colis enregistré avec succès sur le trajet " . $trajet['code'] . " (" . $trajet['libelle'] . ").");
        header('Location: ' . View::url('colisage/parcels/' . $newParcelId));
        exit;
    }
}
