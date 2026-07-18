<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Colisage\ColisageRepository;
use App\Services\Colisage\ColisageService;
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
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $data = $this->service->listParcels($filters, $page);

        // Fetch sites/agences
        $sitesStmt = Database::getConnection()->query("SELECT id, name FROM company_sites WHERE is_active = 1");
        $sites = $sitesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->colisageView('colisage/parcels/index', 'Gestion des Colis', 'operations', [
            'page' => new ColisageIndexPage(array_replace($data, ['filters' => $filters]), $sites),
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::check();

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

        $this->colisageView('colisage/parcels/create', 'Enregistrer un Colis', 'operations', [
            'sites' => $sites,
            'clients' => $clients,
            'products' => $products,
            'tauxChangeEur' => $tauxChangeEur,
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
            'type_expediteur' => $_POST['type_expediteur'] ?? 'export_aerien',
            'marchandises' => $marchandises,
        ]);

        header('Location: ' . View::url('colisage/parcels/' . $newId));
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

    public function withdraw(int $id): void
    {
        AuthMiddleware::check();

        $this->service->withdrawParcel($id, [
            'recup_nom' => $_POST['recup_nom'] ?? '',
            'recup_cni' => $_POST['recup_cni'] ?? '',
            'recup_telephone' => $_POST['recup_telephone'] ?? '',
        ]);

        header('Location: ' . View::url('colisage/parcels/' . $id));
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

    public function groupageAddParcel(int $id): void
    {
        AuthMiddleware::check();

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

        $this->service->startExpedition($id);

        header('Location: ' . View::url('colisage/groupage/' . $id));
        exit;
    }

    public function groupageArrive(int $id): void
    {
        AuthMiddleware::check();

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
}
