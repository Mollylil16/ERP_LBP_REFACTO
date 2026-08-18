<?php

declare(strict_types=1);

namespace App\Controllers\Surveillance;

use App\Controllers\BaseController;
use App\Middleware\SurveillanceAccessMiddleware;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Models\Database;
use App\Repositories\Surveillance\SurveillanceRepository;
use App\Services\Surveillance\SurveillanceService;
use App\Services\Shared\AuditLogService;

final class SurveillanceController extends BaseController
{
    private SurveillanceService $service;

    public function __construct()
    {
        $db = Database::getConnection();
        $this->service = new SurveillanceService(new SurveillanceRepository($db));
    }

    /**
     * Tableau de bord principal de surveillance.
     */
    public function dashboard(): void
    {
        SurveillanceAccessMiddleware::check();

        $filters = [
            'statut' => $_GET['statut'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'regle_code' => $_GET['regle_code'] ?? '',
            'start_date' => $_GET['start_date'] ?? '',
            'end_date' => $_GET['end_date'] ?? '',
        ];

        $data = $this->service->getDashboardData($filters);

        // Fetch users to populate user filter dropdown
        $db = Database::getConnection();
        $users = $db->query("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
        $nbRecPending = (int) $db->query("SELECT COUNT(*) FROM lbp_recommandations_ia WHERE statut = 'en_attente'")->fetchColumn();

        $this->view('surveillance/dashboard', [
            'pageTitle' => 'Centre de Surveillance DG',
            'moduleName' => 'Pilotage DG',
            'moduleCode' => 'pilotage-dg',
            'activeModule' => 'anomalies',
            'additionalStyles' => ['css/finea-ui.css'],
            'stats' => $data['stats'],
            'alerts' => $data['alerts'],
            'employees' => $data['employees'],
            'trend' => $data['trend'],
            'rules' => $data['rules'],
            'filters' => $filters,
            'users' => $users,
            'nbRecPending' => $nbRecPending,
            'activeSubmenu' => 'dashboard',
        ]);
    }

    /**
     * Détails d'une alerte d'intégrité.
     */
    public function alerteShow(string $id): void
    {
        SurveillanceAccessMiddleware::check();

        $idInt = (int) $id;
        $alert = $this->service->getAlertDetail($idInt);

        if (!$alert) {
            Session::flash('error', "Alerte de surveillance introuvable.");
            $this->redirect('surveillance');
        }

        $this->view('surveillance/alerte_detail', [
            'pageTitle' => "Alerte #" . $alert['id'] . " - Intégrité",
            'moduleName' => 'Pilotage DG',
            'moduleCode' => 'pilotage-dg',
            'activeModule' => 'anomalies',
            'additionalStyles' => ['css/finea-ui.css'],
            'alert' => $alert,
            'activeSubmenu' => 'alertes',
        ]);
    }

    /**
     * Traite (confirme ou justifie) une alerte.
     */
    public function alerteTraiter(string $id): void
    {
        SurveillanceAccessMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            $this->redirect("surveillance/alertes/{$id}");
        }

        $idInt = (int) $id;
        $statut = $_POST['statut'] ?? '';
        $commentaire = trim($_POST['commentaire_dg'] ?? '');
        $dgUserId = (int) Auth::id();

        if (empty($statut) || empty($commentaire)) {
            Session::flash('error', "Le statut et le commentaire de décision sont obligatoires.");
            $this->redirect("surveillance/alertes/{$id}");
        }

        try {
            $success = $this->service->processAlert($idInt, $statut, $commentaire, $dgUserId);
            if ($success) {
                Session::flash('success', "Alerte traitée avec succès (" . ($statut === 'confirmee' ? 'Confirmée' : 'Justifiée') . ").");
            } else {
                Session::flash('error', "Une erreur s'est produite lors du traitement de l'alerte.");
            }
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect("surveillance/alertes/{$id}");
    }

    /**
     * Profil détaillé d'un employé (Historique d'intégrité).
     */
    public function employeShow(string $id): void
    {
        SurveillanceAccessMiddleware::check();

        $userId = (int) $id;
        $profile = $this->service->getEmployeeProfile($userId);

        if (!$profile) {
            Session::flash('error', "Employé introuvable ou inactif.");
            $this->redirect('surveillance');
        }

        $this->view('surveillance/employe_detail', [
            'pageTitle' => "Profil Intégrité - " . $profile['employee']['full_name'],
            'moduleName' => 'Pilotage DG',
            'moduleCode' => 'pilotage-dg',
            'activeModule' => 'anomalies',
            'additionalStyles' => ['css/finea-ui.css'],
            'profile' => $profile,
            'activeSubmenu' => 'employes',
        ]);
    }

    /**
     * Page de configuration des règles de détection.
     */
    public function configRegles(): void
    {
        SurveillanceAccessMiddleware::check();

        $rules = $this->service->getRulesConfig();

        $this->view('surveillance/config_regles', [
            'pageTitle' => 'Configuration des Règles d\'Intégrité',
            'moduleName' => 'Pilotage DG',
            'moduleCode' => 'pilotage-dg',
            'activeModule' => 'anomalies',
            'additionalStyles' => ['css/finea-ui.css'],
            'rules' => $rules,
            'activeSubmenu' => 'config',
        ]);
    }

    /**
     * Met à jour la configuration d'une règle.
     */
    public function configReglesUpdate(string $code): void
    {
        SurveillanceAccessMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            $this->redirect('surveillance/config');
        }

        $isActive = !empty($_POST['is_active']);
        $gravite = $_POST['gravite'] ?? 'moyen';
        $paramsArray = $_POST['parametres'] ?? [];

        // Cast numérique approprié pour les valeurs de paramètres
        foreach ($paramsArray as $k => $v) {
            if (is_numeric($v)) {
                $paramsArray[$k] = strpos($v, '.') !== false ? (float)$v : (int)$v;
            }
        }

        try {
            $success = $this->service->updateRule($code, $isActive, $gravite, $paramsArray);
            if ($success) {
                Session::flash('success', "Configuration de la règle {$code} mise à jour.");
            } else {
                Session::flash('error', "Erreur lors de la mise à jour de la règle.");
            }
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('surveillance/config');
    }

    /**
     * Script de vérification de l'intégrité de la chaîne d'audit en direct.
     */
    public function verifyIntegrite(): void
    {
        SurveillanceAccessMiddleware::check();

        $result = AuditLogService::verifyChainIntegrity();

        $this->view('surveillance/verify_integrite', [
            'pageTitle' => 'Audit Trail — Vérification Cryptographique',
            'moduleName' => 'Pilotage DG',
            'moduleCode' => 'pilotage-dg',
            'activeModule' => 'anomalies',
            'additionalStyles' => ['css/finea-ui.css'],
            'result' => $result,
            'activeSubmenu' => 'integrite',
        ]);
    }

    /**
     * Export PDF du rapport mensuel d'intégrité.
     */
    public function exportPdf(): void
    {
        SurveillanceAccessMiddleware::check();

        $filters = [
            'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
            'end_date' => $_GET['end_date'] ?? date('Y-m-t'),
        ];

        $data = $this->service->getDashboardData($filters);

        // Cette vue contient le format A4 imprimable natif du système LBP
        $this->view('surveillance/export_pdf', [
            'pageTitle' => "Rapport Intégrité — " . date('M Y'),
            'stats' => $data['stats'],
            'alerts' => $data['alerts'],
            'employees' => $data['employees'],
            'filters' => $filters,
        ]);
    }

    /**
     * Export Excel/CSV du rapport d'alertes.
     */
    public function exportExcel(): void
    {
        SurveillanceAccessMiddleware::check();

        $filters = [
            'statut' => $_GET['statut'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'regle_code' => $_GET['regle_code'] ?? '',
            'start_date' => $_GET['start_date'] ?? '',
            'end_date' => $_GET['end_date'] ?? '',
        ];

        $data = $this->service->getDashboardData($filters);
        $alerts = $data['alerts'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rapport_alertes_integrite_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            'ID Alerte', 'Collaborateur', 'Règle', 'Gravité', 
            'Statut', 'Entité Cible', 'ID Entité', 'Commentaire DG', 
            'Date Détection', 'Date Traitement'
        ], ';');

        foreach ($alerts as $a) {
            fputcsv($output, [
                $a['id'],
                $a['user_name'] ?? 'Inconnu',
                $a['regle_titre'] ?? $a['regle_code'],
                strtoupper($a['gravite']),
                strtoupper($a['statut']),
                $a['entity_type'] ?? 'N/A',
                $a['entity_id'] ?? 'N/A',
                $a['commentaire_dg'] ?? '',
                $a['created_at'],
                $a['traite_at'] ?? ''
            ], ';');
        }

        fclose($output);
        exit;
    }

    /**
     * File de validation des recommandations générées par l'IA.
     */
    public function recommandations(): void
    {
        SurveillanceAccessMiddleware::check();

        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT r.*, u.full_name AS user_name
            FROM lbp_recommandations_ia r
            JOIN users u ON r.user_id = u.id
            WHERE r.statut = 'en_attente'
            ORDER BY r.created_at DESC
        ");
        $recommandations = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->view('surveillance/recommandations', [
            'pageTitle' => 'Recommandations IA',
            'moduleName' => 'Pilotage DG',
            'moduleCode' => 'pilotage-dg',
            'activeModule' => 'anomalies',
            'additionalStyles' => ['css/finea-ui.css'],
            'recommandations' => $recommandations,
            'activeSubmenu' => 'recommandations',
        ]);
    }

    /**
     * Approuve et applique une recommandation IA (ex: suspension de compte).
     */
    public function approuverRecommandation(string $id): void
    {
        SurveillanceAccessMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            $this->redirect('surveillance/recommandations');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM lbp_recommandations_ia WHERE id = :id AND statut = 'en_attente'");
        $stmt->execute(['id' => (int) $id]);
        $rec = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$rec) {
            Session::flash('error', 'Recommandation introuvable ou déjà traitée.');
            $this->redirect('surveillance/recommandations');
        }

        $db->beginTransaction();
        try {
            // 1. Mettre à jour le statut de la recommandation
            $upStmt = $db->prepare("
                UPDATE lbp_recommandations_ia 
                SET statut = 'approuvee', traite_par = :dg_id, traite_at = NOW() 
                WHERE id = :id
            ");
            $upStmt->execute(['dg_id' => (int) Auth::id(), 'id' => $rec['id']]);

            // 2. Appliquer l'action recommandée
            if ($rec['action_recommandee'] === 'suspendre_compte') {
                $userRepo = new \App\Repositories\Admin\UserRepository($db);
                $userRepo->setUserActive((int) $rec['user_id'], false, (int) Auth::id());
                
                AuditLogService::log(
                    'ia_recommendation_approved', 
                    'users', 
                    (int) $rec['user_id'], 
                    ['status' => 'active'], 
                    ['status' => 'inactive', 'recommandation_id' => $rec['id']]
                );
            } elseif ($rec['action_recommandee'] === 'qualifier_fraude') {
                // Créer une alerte d'intégrité confirmée dans la table lbp_alertes_integrite
                $insAlert = $db->prepare("
                    INSERT INTO lbp_alertes_integrite (
                        user_id, regle_code, gravite, entity_type, entity_id, 
                        contexte, statut, origine_decision, commentaire_dg, created_at, traite_at, traite_par
                    ) VALUES (
                        :user_id, 'IA_FRAUDE_QUALIFIEE', 'grave', 'users', :user_id,
                        :contexte, 'confirmee', 'validee_dg', :commentaire, NOW(), NOW(), :dg_id
                    )
                ");
                $insAlert->execute([
                    'user_id' => (int) $rec['user_id'],
                    'contexte' => json_encode(['explication_ia' => $rec['explication']], JSON_UNESCAPED_UNICODE),
                    'commentaire' => 'Approuvé par le DG depuis la recommandation IA.',
                    'dg_id' => (int) Auth::id()
                ]);

                AuditLogService::log(
                    'ia_recommendation_approved', 
                    'lbp_alertes_integrite', 
                    (int) $db->lastInsertId(), 
                    null, 
                    ['action' => 'qualifier_fraude', 'recommandation_id' => $rec['id']]
                );
            }

            $db->commit();
            Session::flash('success', 'La recommandation a été approuvée et appliquée avec succès.');
        } catch (\Exception $e) {
            $db->rollBack();
            Session::flash('error', 'Erreur lors de l\'approbation : ' . $e->getMessage());
        }

        $this->redirect('surveillance/recommandations');
    }

    /**
     * Rejette une recommandation de l'IA.
     */
    public function rejeterRecommandation(string $id): void
    {
        SurveillanceAccessMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            $this->redirect('surveillance/recommandations');
        }

        $db = Database::getConnection();
        $upStmt = $db->prepare("
            UPDATE lbp_recommandations_ia 
            SET statut = 'rejetee', traite_par = :dg_id, traite_at = NOW() 
            WHERE id = :id AND statut = 'en_attente'
        ");
        $success = $upStmt->execute(['dg_id' => (int) Auth::id(), 'id' => (int) $id]);

        if ($success) {
            AuditLogService::log('ia_recommendation_rejected', 'lbp_recommandations_ia', (int) $id, null, null);
            Session::flash('success', 'La recommandation a été rejetée.');
        } else {
            Session::flash('error', 'Une erreur s\'est produite lors du rejet de la recommandation.');
        }

        $this->redirect('surveillance/recommandations');
    }

    /**
     * Action manuelle pour déclencher le réentraînement des modèles ML.
     */
    public function trainMl(): void
    {
        SurveillanceAccessMiddleware::check();

        $mlService = new \App\Services\Surveillance\MLIntegrationService();
        $result = $mlService->trainModels();

        if ($result && isset($result['status']) && $result['status'] === 'success') {
            Session::flash('success', 'Les modèles ML ont été ré-entraînés avec succès (échantillons : ' . $result['samples_trained'] . ').');
        } else {
            Session::flash('error', 'Erreur lors du réentraînement du modèle. Veuillez vérifier que le micro-service Python est en cours d\'exécution.');
        }

        $this->redirect('surveillance');
    }
}
