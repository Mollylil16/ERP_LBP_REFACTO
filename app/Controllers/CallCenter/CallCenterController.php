<?php

declare(strict_types=1);

namespace App\Controllers\CallCenter;

use App\Controllers\BaseController;
use App\Middleware\PermissionMiddleware;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Database;
use PDO;

final class CallCenterController extends BaseController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function index(): void
    {
        PermissionMiddleware::check('call_center_view');

        // 1. Calculer les KPIs
        // Total appels
        $stmt = $this->db->query("SELECT COUNT(*) FROM lbp_call_center_appels");
        $totalAppels = (int)$stmt->fetchColumn();

        // Note satisfaction moyenne
        $stmt = $this->db->query("SELECT COALESCE(AVG(satisfaction_score), 0.0) FROM lbp_call_center_appels WHERE satisfaction_score IS NOT NULL");
        $avgSatisfaction = round((float)$stmt->fetchColumn(), 1);

        // Litiges ouverts
        $stmt = $this->db->query("SELECT COUNT(*) FROM lbp_call_center_litiges WHERE statut NOT IN ('resolu', 'annule')");
        $openLitiges = (int)$stmt->fetchColumn();

        // Taux de résolution des litiges
        $stmt = $this->db->query("
            SELECT 
                COALESCE(
                    (COUNT(CASE WHEN statut = 'resolu' THEN 1 END) * 100.0) / NULLIF(COUNT(*), 0),
                    0.0
                )
            FROM lbp_call_center_litiges
        ");
        $resolutionRate = round((float)$stmt->fetchColumn(), 1);

        $kpis = [
            'total_appels' => $totalAppels,
            'avg_satisfaction' => $avgSatisfaction,
            'open_litiges' => $openLitiges,
            'resolution_rate' => $resolutionRate,
        ];

        // 2. Récupérer les données récentes
        $stmt = $this->db->query("
            SELECT a.*, c.name as client_name, u.full_name as agent_name 
            FROM lbp_call_center_appels a 
            INNER JOIN lbp_clients c ON a.client_id = c.id 
            INNER JOIN users u ON a.agent_id = u.id 
            ORDER BY a.id DESC 
            LIMIT 5
        ");
        $recentAppels = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->query("
            SELECT l.*, c.name as client_name, u.full_name as agent_name, col.tracking_number 
            FROM lbp_call_center_litiges l 
            INNER JOIN lbp_clients c ON l.client_id = c.id 
            INNER JOIN users u ON l.agent_id = u.id 
            LEFT JOIN lbp_colis col ON l.colis_id = col.id 
            ORDER BY l.id DESC 
            LIMIT 5
        ");
        $recentLitiges = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('call_center/dashboard', $this->viewData() + [
            'kpis' => $kpis,
            'recentAppels' => $recentAppels,
            'recentLitiges' => $recentLitiges,
        ]);
    }

    public function appels(): void
    {
        PermissionMiddleware::check('call_center_view');

        $stmt = $this->db->query("
            SELECT a.*, c.name as client_name, u.full_name as agent_name 
            FROM lbp_call_center_appels a 
            INNER JOIN lbp_clients c ON a.client_id = c.id 
            INNER JOIN users u ON a.agent_id = u.id 
            ORDER BY a.id DESC
        ");
        $appels = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->query("SELECT id, name FROM lbp_clients ORDER BY name ASC");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('call_center/appels', $this->viewData() + [
            'appels' => $appels,
            'clients' => $clients,
        ]);
    }

    public function storeAppel(): void
    {
        PermissionMiddleware::check('call_center_manage');

        $clientId = $_POST['client_id'] ?? null;
        $typeAppel = $_POST['type_appel'] ?? null;
        $description = $_POST['description'] ?? null;
        $statut = $_POST['statut'] ?? 'traite';
        $satisfaction = $_POST['satisfaction_score'] ?? null;

        if (!$clientId || !$typeAppel || !$description) {
            Session::flash('error', 'Veuillez remplir tous les champs obligatoires.');
            $this->back();
        }

        $stmt = $this->db->prepare("
            INSERT INTO lbp_call_center_appels (client_id, agent_id, type_appel, description, statut, satisfaction_score, created_at)
            VALUES (:client_id, :agent_id, :type_appel, :description, :statut, :satisfaction_score, NOW())
        ");
        $stmt->execute([
            'client_id' => $clientId,
            'agent_id' => Auth::id(),
            'type_appel' => $typeAppel,
            'description' => $description,
            'statut' => $statut,
            'satisfaction_score' => $satisfaction !== '' ? (int)$satisfaction : null,
        ]);

        Session::flash('success', 'Appel de suivi enregistré avec succès.');
        $this->redirect('/call-center/appels');
    }

    public function litiges(): void
    {
        PermissionMiddleware::check('call_center_view');

        $stmt = $this->db->query("
            SELECT l.*, c.name as client_name, u.full_name as agent_name, col.tracking_number 
            FROM lbp_call_center_litiges l 
            INNER JOIN lbp_clients c ON l.client_id = c.id 
            INNER JOIN users u ON l.agent_id = u.id 
            LEFT JOIN lbp_colis col ON l.colis_id = col.id 
            ORDER BY l.id DESC
        ");
        $litiges = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->query("SELECT id, name FROM lbp_clients ORDER BY name ASC");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->query("SELECT id, tracking_number FROM lbp_colis ORDER BY id DESC LIMIT 100");
        $colis = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('call_center/litiges', $this->viewData() + [
            'litiges' => $litiges,
            'clients' => $clients,
            'colis' => $colis,
        ]);
    }

    public function storeLitige(): void
    {
        PermissionMiddleware::check('call_center_manage');

        $clientId = $_POST['client_id'] ?? null;
        $colisId = $_POST['colis_id'] ?? null;
        $typeLitige = $_POST['type_litige'] ?? null;
        $description = $_POST['description'] ?? null;
        $gravite = $_POST['gravite'] ?? 'moyenne';

        if (!$clientId || !$typeLitige || !$description) {
            Session::flash('error', 'Veuillez remplir tous les champs obligatoires.');
            $this->back();
        }

        $stmt = $this->db->prepare("
            INSERT INTO lbp_call_center_litiges (client_id, colis_id, agent_id, type_litige, description, gravite, statut, date_ouverture)
            VALUES (:client_id, :colis_id, :agent_id, :type_litige, :description, :gravite, 'nouveau', NOW())
        ");
        $stmt->execute([
            'client_id' => $clientId,
            'colis_id' => $colisId !== '' ? $colisId : null,
            'agent_id' => Auth::id(),
            'type_litige' => $typeLitige,
            'description' => $description,
            'gravite' => $gravite,
        ]);

        Session::flash('success', 'Réclamation/litige ouvert avec succès.');
        $this->redirect('/call-center/litiges');
    }

    public function resolveLitige(): void
    {
        PermissionMiddleware::check('call_center_manage');

        $id = $this->getRouteParam('id');
        $solution = $_POST['solution_apporte'] ?? null;
        $statut = $_POST['statut'] ?? 'resolu';

        if (!$id || !$solution) {
            Session::flash('error', 'La solution apportée est obligatoire pour traiter le litige.');
            $this->back();
        }

        $stmt = $this->db->prepare("
            UPDATE lbp_call_center_litiges 
            SET solution_apporte = :solution, statut = :statut, date_resolution = CASE WHEN :statut = 'resolu' THEN NOW() ELSE NULL END
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'solution' => $solution,
            'statut' => $statut,
        ]);

        Session::flash('success', 'Le statut du litige a été mis à jour.');
        $this->redirect('/call-center/litiges');
    }

    /**
     * Helper pour extraire les paramètres de route nommés si nécessaire.
     */
    private function getRouteParam(string $name): ?string
    {
        $uri = $_SERVER['REQUEST_URI'];
        // Les routes avec paramètres sont sous la forme /call-center/litiges/{id}/resoudre
        if (preg_match('#/call-center/litiges/(\d+)/resoudre#', $uri, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function viewData(): array
    {
        return [
            'pageTitle' => 'Call Center & Support Client',
            'moduleName' => 'Call Center',
            'moduleCode' => 'CAL',
            'moduleTheme' => [
                'accent' => '#0ea5e9',
                'accent2' => '#0369a1',
                'gradient' => 'linear-gradient(135deg, #0369a1, #0ea5e9)',
            ],
            'moduleNavigation' => [
                ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => '/call-center/dashboard', 'available' => true],
                ['key' => 'appels', 'label' => 'Journal des Appels', 'icon' => 'CAL', 'url' => '/call-center/appels', 'available' => true],
                ['key' => 'litiges', 'label' => 'Réclamations & Litiges', 'icon' => 'LTG', 'url' => '/call-center/litiges', 'available' => true],
            ],
            'additionalStyles' => ['css/finea-ui.css', 'css/finance.css'],
        ];
    }
}
