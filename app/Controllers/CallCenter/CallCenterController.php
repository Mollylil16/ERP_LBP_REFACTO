<?php

declare(strict_types=1);

namespace App\Controllers\CallCenter;

use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Models\Database;
use PDO;

final class CallCenterController extends BaseController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ==========================================
    // DASHBOARD
    // ==========================================

    public function index(): void
    {
        AuthMiddleware::check();
        if (!Auth::can('call_center_view')) {
            Session::flash('error', 'Accès refusé au module Call Center.');
            $this->redirect('portal');
        }

        // KPIs
        $stmt = $this->db->query("SELECT COUNT(*) FROM lbp_call_center_appels");
        $totalAppels = $stmt ? (int) $stmt->fetchColumn() : 0;

        $stmt = $this->db->query("SELECT COUNT(*) FROM lbp_call_center_appels WHERE DATE(created_at) = CURDATE()");
        $appelsAujourd = $stmt ? (int) $stmt->fetchColumn() : 0;

        $stmt = $this->db->query("SELECT COALESCE(AVG(satisfaction_score), 0) FROM lbp_call_center_appels WHERE satisfaction_score IS NOT NULL");
        $avgSatisfaction = $stmt ? round((float) $stmt->fetchColumn(), 1) : 0.0;

        $stmt = $this->db->query("SELECT COUNT(*) FROM lbp_call_center_litiges WHERE statut NOT IN ('resolu', 'annule')");
        $openLitiges = $stmt ? (int) $stmt->fetchColumn() : 0;

        $stmt = $this->db->query("SELECT COUNT(*) FROM lbp_call_center_litiges WHERE statut = 'nouveau'");
        $newLitiges = $stmt ? (int) $stmt->fetchColumn() : 0;

        $stmt = $this->db->query("
            SELECT
                COALESCE(
                    ROUND((COUNT(CASE WHEN statut = 'resolu' THEN 1 END) * 100.0) / NULLIF(COUNT(*), 0), 1),
                    0.0
                ) as rate
            FROM lbp_call_center_litiges
        ");
        $resolutionRate = $stmt ? (float) $stmt->fetchColumn() : 0.0;

        $kpis = [
            'total_appels'      => $totalAppels,
            'appels_aujourdhui' => $appelsAujourd,
            'avg_satisfaction'  => $avgSatisfaction,
            'open_litiges'      => $openLitiges,
            'new_litiges'       => $newLitiges,
            'resolution_rate'   => $resolutionRate,
        ];

        // 5 derniers appels
        $stmt = $this->db->query("
            SELECT a.*, c.name AS client_name, u.full_name AS agent_name
            FROM lbp_call_center_appels a
            LEFT JOIN lbp_clients c ON a.client_id = c.id
            LEFT JOIN users u ON a.agent_id = u.id
            ORDER BY a.id DESC
            LIMIT 5
        ");
        $recentAppels = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 5 litiges récents
        $stmt = $this->db->query("
            SELECT l.*, c.name AS client_name, u.full_name AS agent_name,
                   col.numero_tracking
            FROM lbp_call_center_litiges l
            LEFT JOIN lbp_clients c ON l.client_id = c.id
            LEFT JOIN users u ON l.agent_id = u.id
            LEFT JOIN lbp_colis col ON l.colis_id = col.id
            ORDER BY l.id DESC
            LIMIT 5
        ");
        $recentLitiges = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->callCenterView('call_center/dashboard', 'Tableau de bord Call Center', 'dashboard', [
            'kpis'          => $kpis,
            'recentAppels'  => $recentAppels,
            'recentLitiges' => $recentLitiges,
        ]);
    }

    // ==========================================
    // APPELS
    // ==========================================

    public function appels(): void
    {
        AuthMiddleware::check();
        if (!Auth::can('call_center_view')) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('portal');
        }

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin   = $_GET['date_fin'] ?? date('Y-m-d');
        $typeFilter = $_GET['type_appel'] ?? '';

        $sql = "
            SELECT a.*, c.name AS client_name, u.full_name AS agent_name
            FROM lbp_call_center_appels a
            LEFT JOIN lbp_clients c ON a.client_id = c.id
            LEFT JOIN users u ON a.agent_id = u.id
            WHERE DATE(a.created_at) BETWEEN :date_debut AND :date_fin
        ";
        $params = ['date_debut' => $dateDebut, 'date_fin' => $dateFin];

        if ($typeFilter !== '') {
            $sql .= " AND a.type_appel = :type_appel";
            $params['type_appel'] = $typeFilter;
        }

        $sql .= " ORDER BY a.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $appels = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtClients = $this->db->query("SELECT id, name FROM lbp_clients ORDER BY name ASC LIMIT 500");
        $clients = $stmtClients ? $stmtClients->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->callCenterView('call_center/appels', 'Journal des Appels', 'appels', [
            'appels'     => $appels,
            'clients'    => $clients,
            'dateDebut'  => $dateDebut,
            'dateFin'    => $dateFin,
            'typeFilter' => $typeFilter,
        ]);
    }

    public function storeAppel(): void
    {
        AuthMiddleware::check();
        if (!Auth::can('call_center_manage')) {
            Session::flash('error', 'Permission insuffisante pour enregistrer un appel.');
            $this->redirect('call-center/appels');
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('call-center/appels');
        }

        $clientId      = (int) ($_POST['client_id'] ?? 0);
        $typeAppel     = $_POST['type_appel'] ?? 'information';
        $description   = trim((string) ($_POST['description'] ?? ''));
        $statut        = $_POST['statut'] ?? 'traite';
        $satisfaction  = !empty($_POST['satisfaction_score']) ? (int) $_POST['satisfaction_score'] : null;
        $tracking      = trim((string) ($_POST['numero_tracking'] ?? ''));

        if ($clientId === 0 || $description === '') {
            Session::flash('error', 'Veuillez renseigner le client et la description de l\'appel.');
            $this->redirect('call-center/appels');
        }

        $stmt = $this->db->prepare("
            INSERT INTO lbp_call_center_appels
                (client_id, agent_id, type_appel, description, statut, satisfaction_score, numero_tracking, created_at)
            VALUES
                (:client_id, :agent_id, :type_appel, :description, :statut, :satisfaction, :tracking, NOW())
        ");
        $stmt->execute([
            'client_id'  => $clientId,
            'agent_id'   => (int) Auth::id(),
            'type_appel' => $typeAppel,
            'description' => $description,
            'statut'      => $statut,
            'satisfaction' => $satisfaction,
            'tracking'     => $tracking ?: null,
        ]);

        Session::flash('success', 'Appel enregistré avec succès.');
        $this->redirect('call-center/appels');
    }

    // ==========================================
    // LITIGES
    // ==========================================

    public function litiges(): void
    {
        AuthMiddleware::check();
        if (!Auth::can('call_center_view')) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('portal');
        }

        $statutFilter = $_GET['statut'] ?? '';
        $graviteFilter = $_GET['gravite'] ?? '';

        $sql = "
            SELECT l.*, c.name AS client_name, u.full_name AS agent_name,
                   col.numero_tracking, col.assurance_souscrite, col.valeur_declaree, col.montant_assurance, col.devise AS colis_devise
            FROM lbp_call_center_litiges l
            LEFT JOIN lbp_clients c ON l.client_id = c.id
            LEFT JOIN users u ON l.agent_id = u.id
            LEFT JOIN lbp_colis col ON l.colis_id = col.id
            WHERE 1=1
        ";
        $params = [];

        if ($statutFilter !== '') {
            $sql .= " AND l.statut = :statut";
            $params['statut'] = $statutFilter;
        }
        if ($graviteFilter !== '') {
            $sql .= " AND l.gravite = :gravite";
            $params['gravite'] = $graviteFilter;
        }
        $sql .= " ORDER BY FIELD(l.gravite, 'critique', 'elevee', 'moyenne', 'faible'), l.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $litiges = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtClients = $this->db->query("SELECT id, name FROM lbp_clients ORDER BY name ASC LIMIT 500");
        $clients = $stmtClients ? $stmtClients->fetchAll(PDO::FETCH_ASSOC) : [];

        $stmtColis = $this->db->query("SELECT id, numero_tracking FROM lbp_colis ORDER BY id DESC LIMIT 200");
        $colis = $stmtColis ? $stmtColis->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->callCenterView('call_center/litiges', 'Réclamations & Litiges', 'litiges', [
            'litiges'       => $litiges,
            'clients'       => $clients,
            'colis'         => $colis,
            'statutFilter'  => $statutFilter,
            'graviteFilter' => $graviteFilter,
        ]);
    }

    public function storeLitige(): void
    {
        AuthMiddleware::check();
        if (!Auth::can('call_center_manage')) {
            Session::flash('error', 'Permission insuffisante.');
            $this->redirect('call-center/litiges');
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('call-center/litiges');
        }

        $clientId    = (int) ($_POST['client_id'] ?? 0);
        $colisId     = !empty($_POST['colis_id']) ? (int) $_POST['colis_id'] : null;
        $typeLitige  = $_POST['type_litige'] ?? 'autre';
        $description = trim((string) ($_POST['description'] ?? ''));
        $gravite     = $_POST['gravite'] ?? 'moyenne';

        if ($clientId === 0 || $description === '') {
            Session::flash('error', 'Client et description sont obligatoires pour ouvrir un litige.');
            $this->redirect('call-center/litiges');
        }

        $stmt = $this->db->prepare("
            INSERT INTO lbp_call_center_litiges
                (client_id, colis_id, agent_id, type_litige, description, gravite, statut, date_ouverture)
            VALUES
                (:client_id, :colis_id, :agent_id, :type_litige, :description, :gravite, 'nouveau', NOW())
        ");
        $stmt->execute([
            'client_id'   => $clientId,
            'colis_id'    => $colisId,
            'agent_id'    => (int) Auth::id(),
            'type_litige' => $typeLitige,
            'description' => $description,
            'gravite'     => $gravite,
        ]);

        Session::flash('success', 'Litige/réclamation ouvert(e) avec succès.');
        $this->redirect('call-center/litiges');
    }

    public function resolveLitige(int $id): void
    {
        AuthMiddleware::check();
        if (!Auth::can('call_center_manage')) {
            Session::flash('error', 'Permission insuffisante.');
            $this->redirect('call-center/litiges');
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('call-center/litiges');
        }

        $solution = trim((string) ($_POST['solution_apportee'] ?? ''));
        $statut   = $_POST['statut'] ?? 'resolu';

        if ($solution === '') {
            Session::flash('error', 'La solution apportée est obligatoire.');
            $this->redirect('call-center/litiges');
        }

        $stmt = $this->db->prepare("
            UPDATE lbp_call_center_litiges
            SET solution_apportee = :solution,
                statut = :statut,
                date_resolution = CASE WHEN :statut2 = 'resolu' THEN NOW() ELSE NULL END
            WHERE id = :id
        ");
        $stmt->execute([
            'id'       => $id,
            'solution' => $solution,
            'statut'   => $statut,
            'statut2'  => $statut,
        ]);

        Session::flash('success', 'Le litige #' . $id . ' a été mis à jour.');
        $this->redirect('call-center/litiges');
    }

    // ==========================================
    // VUE RAYONS EN TEMPS RÉEL
    // ==========================================

    public function rayons(): void
    {
        AuthMiddleware::check();
        if (!Auth::can('call_center_view')) {
            Session::flash('error', 'Accès refusé.');
            $this->redirect('portal');
        }

        $agenceId = isset($_GET['agence_id']) && (int) $_GET['agence_id'] > 0
            ? (int) $_GET['agence_id']
            : null;

        // Tous les sites actifs
        $stmtSites = $this->db->query("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        $sites = $stmtSites ? $stmtSites->fetchAll(PDO::FETCH_ASSOC) : [];

        // Rayons avec capacité calculée
        $sql = "
            SELECT r.*,
                   s.name AS agence_nom,
                   (SELECT COUNT(*) FROM lbp_colis c
                    WHERE c.rayon_id = r.id AND c.statut NOT IN ('LIVRÉ', 'ANNULÉ', 'RETIRÉ')) AS capacite_occupee
            FROM logistique_rayons r
            LEFT JOIN company_sites s ON r.agence_id = s.id
        ";
        $params = [];
        if ($agenceId !== null) {
            $sql .= " WHERE r.agence_id = :agence_id";
            $params['agence_id'] = $agenceId;
        }
        $sql .= " ORDER BY r.agence_id ASC, r.code_rayon ASC";

        $stmtRayons = $this->db->prepare($sql);
        $stmtRayons->execute($params);
        $rayons = $stmtRayons->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Colis actifs groupés par rayon
        $colisParRayon = [];
        if (!empty($rayons)) {
            $rayonIds = array_column($rayons, 'id');
            $placeholders = implode(',', array_fill(0, count($rayonIds), '?'));
            $stmtColis = $this->db->prepare("
                SELECT c.rayon_id, c.id, c.numero_tracking, c.statut,
                       c.date_limite_retrait,
                       cl_dest.name AS destinataire_nom,
                       cl_dest.phone AS destinataire_phone,
                       cl_exp.name AS expediteur_nom,
                       DATEDIFF(CURDATE(), c.date_limite_retrait) AS jours_retard
                FROM lbp_colis c
                LEFT JOIN lbp_clients cl_dest ON c.destinataire_id = cl_dest.id
                LEFT JOIN lbp_clients cl_exp ON c.expediteur_id = cl_exp.id
                WHERE c.rayon_id IN ({$placeholders})
                  AND c.statut NOT IN ('LIVRÉ', 'ANNULÉ', 'RETIRÉ')
                ORDER BY c.date_limite_retrait ASC
            ");
            $stmtColis->execute($rayonIds);
            $allColis = $stmtColis->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($allColis as $col) {
                $colisParRayon[(int) $col['rayon_id']][] = $col;
            }
        }

        // Statistiques globales (colis hors délai)
        $stmtRetard = $this->db->query("
            SELECT COUNT(*) FROM lbp_colis
            WHERE statut NOT IN ('LIVRÉ', 'ANNULÉ', 'RETIRÉ')
              AND date_limite_retrait IS NOT NULL
              AND date_limite_retrait < NOW()
        ");
        $colisHorsDelai = $stmtRetard ? (int) $stmtRetard->fetchColumn() : 0;

        $this->callCenterView('call_center/rayons', 'Vue Rayons en Temps Réel', 'rayons', [
            'sites'         => $sites,
            'rayons'        => $rayons,
            'colisParRayon' => $colisParRayon,
            'agenceId'      => $agenceId,
            'colisHorsDelai' => $colisHorsDelai,
            'dernierRefresh' => date('d/m/Y à H:i:s'),
        ]);
    }

    // ==========================================
    // HELPER — Layout
    // ==========================================

    /** @param array<string,mixed> $data */
    private function callCenterView(string $view, string $pageTitle, string $activeModule, array $data = []): void
    {
        $navigation = [
            ['key' => 'dashboard', 'label' => 'Tableau de bord',       'icon' => 'DB',  'url' => '/call-center/dashboard',  'available' => true],
            ['key' => 'appels',    'label' => 'Journal des Appels',     'icon' => 'APL', 'url' => '/call-center/appels',     'available' => true],
            ['key' => 'litiges',   'label' => 'Réclamations & Litiges', 'icon' => 'LTG', 'url' => '/call-center/litiges',    'available' => true],
            ['key' => 'rayons',    'label' => 'Vue Rayons Temps Réel',  'icon' => 'RYN', 'url' => '/call-center/rayons',     'available' => true],
        ];

        $layoutData = [
            'pageTitle'        => $pageTitle,
            'moduleName'       => 'Call Center',
            'moduleCode'       => 'CAL',
            'activeModule'     => $activeModule,
            'moduleNavigation' => $navigation,
            'additionalStyles' => ['css/finea-ui.css', 'css/colisage.css'],
            'moduleTheme'      => [
                'accent'    => '#0ea5e9',
                'accent2'   => '#0369a1',
                'gradient'  => 'linear-gradient(135deg, #0369a1, #0ea5e9)',
            ],
        ];

        $data = array_replace(\App\Support\ViewBag::defaults(), $layoutData, $data);
        $viewData = \App\Support\ViewBag::from($data);
        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/views/' . $view . '.php';
        $content = ob_get_clean();

        require BASE_PATH . '/views/layouts/module.php';
    }
}
