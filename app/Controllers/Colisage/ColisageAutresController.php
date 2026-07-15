<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Colisage\ColisageRepository;
use App\Services\Colisage\ColisageService;
use App\Helpers\View;

final class ColisageAutresController extends ColisageBaseController
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
            'trajet' => $_GET['trajet'] ?? '',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Custom DB query to fetch only "Autres envois" (express shipments)
        $pdo = Database::getConnection();
        
        $sql = "
            SELECT c.*,
                   exp.name AS expediteur_name,
                   dest.name AS destinataire_name,
                   s_dep.name AS agence_depart_name,
                   s_arr.name AS agence_arrivee_name
            FROM lbp_colis c
            JOIN lbp_clients exp ON c.expediteur_id = exp.id
            JOIN lbp_clients dest ON c.destinataire_id = dest.id
            LEFT JOIN company_sites s_dep ON c.agence_depart_id = s_dep.id
            LEFT JOIN company_sites s_arr ON c.agence_arrivee_id = s_arr.id
            WHERE c.type_expediteur IN ('dhl', 'colis_rapide_export', 'colis_rapide_import')
        ";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (c.numero_tracking LIKE :q OR exp.name LIKE :q OR dest.name LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['statut'])) {
            $sql .= " AND c.statut = :statut";
            $params['statut'] = $filters['statut'];
        }
        if (!empty($filters['type_expediteur'])) {
            $sql .= " AND c.type_expediteur = :type_expediteur";
            $params['type_expediteur'] = $filters['type_expediteur'];
        }
        if (!empty($filters['trajet'])) {
            $sql .= " AND c.trajet = :trajet";
            $params['trajet'] = $filters['trajet'];
        }

        // Count query
        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") as t";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalItems = (int) $countStmt->fetchColumn();

        // Data query
        $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $parcelsData = [
            'items' => $items,
            'pagination' => [
                'currentPage' => $page,
                'itemsPerPage' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => max(1, (int) ceil($totalItems / $limit)),
            ],
        ];

        // Fetch sites/agences
        $sitesStmt = $pdo->query("SELECT id, name FROM company_sites WHERE is_active = 1");
        $sites = $sitesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->colisageView('colisage/autres/index', 'Autres envois (Express)', 'autres', [
            'parcelsData' => $parcelsData,
            'filters' => $filters,
            'sites' => $sites,
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::check();

        $pdo = Database::getConnection();
        $sitesStmt = $pdo->query("SELECT id, name FROM company_sites WHERE is_active = 1");
        $sites = $sitesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $clients = $this->service->listClients();
        $products = $this->service->listProducts();

        // Fetch exchange rate EUR -> XOF from database
        $rateStmt = $pdo->prepare("SELECT taux FROM lbp_devises_taux WHERE devise_source = 'EUR' AND devise_cible = 'XOF'");
        $rateStmt->execute();
        $rate = (float) ($rateStmt->fetchColumn() ?: 655.957);

        $this->colisageView('colisage/autres/create', 'Nouvel envoi express', 'autres', [
            'sites' => $sites,
            'clients' => $clients,
            'products' => $products,
            'eur_to_xof_rate' => $rate,
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();

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

        $marchandises = [];
        for ($idx = 0; $idx < 5; $idx++) {
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

        // Determine express type & trajet
        $type = $_POST['type_expediteur'] ?? 'dhl';
        $trajet = null;
        if ($type === 'colis_rapide_export' || $type === 'colis_rapide_import') {
            $trajet = $_POST['trajet'] ?? null;
        }

        // Custom label mapping for print view 'trafic'
        $traficMap = [
            'dhl' => 'DHL Express',
            'colis_rapide_export' => 'Colis Rapide Export',
            'colis_rapide_import' => 'Colis Rapide Import',
        ];
        $trafic = $traficMap[$type] ?? 'Envoi Express';
        if ($trajet) {
            $trajetLabel = str_replace('_', ' ➔ ', $trajet);
            $trafic .= ' (' . $trajetLabel . ')';
        }

        $newId = $this->service->registerParcel([
            'expediteur_id' => $expediteurId,
            'destinataire_id' => $destinataireId,
            'poids_total' => (float) ($_POST['poids_total'] ?? 0.0),
            'nombre_colis' => (int) ($_POST['nombre_colis'] ?? 1),
            'valeur_declaree' => (float) ($_POST['valeur_declaree'] ?? 0.0),
            'montant_total' => (float) ($_POST['valeur_declaree'] ?? 0.0),
            'devise' => $_POST['devise'] ?? 'XOF',
            'agence_depart_id' => !empty($_POST['agence_depart_id']) ? (int) $_POST['agence_depart_id'] : null,
            'agence_arrivee_id' => !empty($_POST['agence_arrivee_id']) ? (int) $_POST['agence_arrivee_id'] : null,
            'type_expediteur' => $type,
            'trafic' => $trafic,
            'marchandises' => $marchandises,
        ]);

        // Save trajet directly to database
        if ($trajet) {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE lbp_colis SET trajet = :trajet WHERE id = :id");
            $stmt->execute(['trajet' => $trajet, 'id' => $newId]);
        }

        header('Location: ' . View::url('colisage/parcels/' . $newId));
        exit;
    }
}
