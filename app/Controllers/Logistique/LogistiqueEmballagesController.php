<?php

declare(strict_types=1);

namespace App\Controllers\Logistique;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Helpers\Csrf;
use App\Models\Database;
use App\Helpers\Session;
use App\Helpers\View;
use App\Helpers\Auth;
use App\Repositories\Logistique\LogistiqueDashboardRepository;
use PDO;

final class LogistiqueEmballagesController extends LogistiqueBaseController
{
    public function index(): void
    {
        AuthMiddleware::check();

        $pdo = Database::getConnection();
        
        $agenceId = isset($_GET['agence_id']) ? (int)$_GET['agence_id'] : null;

        $query = "SELECT c.*, s.agence_id, cs.name as agence_nom, COALESCE(s.quantite_disponible, 0) as quantite_disponible 
                  FROM lbp_emballages_catalogue c 
                  LEFT JOIN lbp_emballages_stocks s ON c.id = s.emballage_id 
                  LEFT JOIN company_sites cs ON s.agence_id = cs.id";
        
        if ($agenceId) {
            $query .= " WHERE s.agence_id = " . (int)$agenceId;
        }
        $query .= " ORDER BY c.type, c.libelle, cs.name";

        $stocks = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        $mouvements = $pdo->query("SELECT m.*, c.libelle as emballage_libelle, c.type as emballage_type, cs.name as agence_nom 
                                   FROM lbp_emballages_mouvements m 
                                   JOIN lbp_emballages_catalogue c ON m.emballage_id = c.id 
                                   JOIN company_sites cs ON m.agence_id = cs.id 
                                   ORDER BY m.created_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

        $sites = $pdo->query("SELECT id, name FROM company_sites WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

        $dashboardRepo = new LogistiqueDashboardRepository($pdo);
        $module = $dashboardRepo->dashboard();

        $this->logistiqueView(
            'logistique/emballages',
            'Gestion des Emballages & Consommables LBP - Logistique',
            'emballages',
            $module,
            [
                'stocks' => $stocks,
                'mouvements' => $mouvements,
                'sites' => $sites,
                'selectedAgenceId' => $agenceId,
            ]
        );
    }

    public function store(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['admin', 'chef_agence', 'agent', 'magasinier', 'agent_logistique', 'superviseur_general']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('logistique/emballages'));
            exit;
        }

        $emballageId = (int)($_POST['emballage_id'] ?? 0);
        $agenceId = (int)($_POST['agence_id'] ?? 1);
        $typeMvt = (string)($_POST['type_mouvement'] ?? 'APPROVISIONNEMENT');
        $quantite = (int)($_POST['quantite'] ?? 0);
        $motif = trim((string)($_POST['motif'] ?? 'Réception commande emballages LBP'));

        if ($emballageId <= 0 || $quantite <= 0) {
            Session::flash('error', 'L\'emballage et la quantité doivent être valides.');
            header('Location: ' . View::url('logistique/emballages'));
            exit;
        }

        $pdo = Database::getConnection();

        // 1. Mettre à jour la quantité disponible en stock
        if ($typeMvt === 'APPROVISIONNEMENT') {
            $stmtStock = $pdo->prepare("INSERT INTO lbp_emballages_stocks (emballage_id, agence_id, quantite_disponible, updated_at)
                                        VALUES (:emb, :site, :qte, NOW())
                                        ON DUPLICATE KEY UPDATE quantite_disponible = quantite_disponible + :qte, updated_at = NOW()");
            $stmtStock->execute(['emb' => $emballageId, 'site' => $agenceId, 'qte' => $quantite]);
        } else {
            // Sortie / Consommation
            $stmtStock = $pdo->prepare("UPDATE lbp_emballages_stocks SET quantite_disponible = GREATEST(0, quantite_disponible - :qte), updated_at = NOW() WHERE emballage_id = :emb AND agence_id = :site");
            $stmtStock->execute(['emb' => $emballageId, 'site' => $agenceId, 'qte' => $quantite]);
        }

        // 2. Enregistrer le mouvement de stock
        $stmtMvt = $pdo->prepare("INSERT INTO lbp_emballages_mouvements (emballage_id, agence_id, type_mouvement, quantite, motif, user_id)
                                  VALUES (:emb, :site, :type, :qte, :motif, :user)");
        $stmtMvt->execute([
            'emb' => $emballageId,
            'site' => $agenceId,
            'type' => $typeMvt,
            'qte' => $quantite,
            'motif' => $motif,
            'user' => Auth::id(),
        ]);

        Session::flash('success', "Le mouvement de stock d'emballage ({$typeMvt} : {$quantite} unités) a été enregistré.");
        header('Location: ' . View::url('logistique/emballages'));
        exit;
    }

    public function creerEmballage(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['admin', 'chef_agence', 'agent', 'magasinier', 'agent_logistique', 'superviseur_general']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('logistique/emballages'));
            exit;
        }

        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $libelle = trim((string)($_POST['libelle'] ?? ''));
        $type = (string)($_POST['type'] ?? 'Carton');
        $dimensions = trim((string)($_POST['dimensions'] ?? ''));
        $prixVente = (float)($_POST['prix_vente_xof'] ?? 0);
        $prixAchat = (float)($_POST['prix_achat_xof'] ?? 0);
        $minAlert = (int)($_POST['min_stock_alerte'] ?? 10);

        if ($code === '' || $libelle === '') {
            Session::flash('error', 'Le code et le libellé de l\'emballage sont obligatoires.');
            header('Location: ' . View::url('logistique/emballages'));
            exit;
        }

        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare("INSERT INTO lbp_emballages_catalogue (code, libelle, type, dimensions, prix_vente_xof, prix_achat_xof, min_stock_alerte) 
                                   VALUES (:code, :libelle, :type, :dimensions, :prix_v, :prix_a, :min_alert)");
            $stmt->execute([
                'code' => $code,
                'libelle' => $libelle,
                'type' => $type,
                'dimensions' => $dimensions,
                'prix_v' => $prixVente,
                'prix_a' => $prixAchat,
                'min_alert' => $minAlert,
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Créer les entrées de stock initial (0) pour toutes les agences actives
            $sites = $pdo->query("SELECT id FROM company_sites WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
            $stmtStock = $pdo->prepare("INSERT INTO lbp_emballages_stocks (emballage_id, agence_id, quantite_disponible, updated_at) VALUES (:emb, :site, 0, NOW())");
            foreach ($sites as $site) {
                $stmtStock->execute(['emb' => $newId, 'site' => $site['id']]);
            }

            Session::flash('success', "L'article d'emballage '{$libelle}' ({$code}) a été créé avec succès.");
        } catch (\Throwable $e) {
            Session::flash('error', 'Erreur lors de la création de l\'emballage : ' . $e->getMessage());
        }

        header('Location: ' . View::url('logistique/emballages'));
        exit;
    }
}
