<?php

declare(strict_types=1);

namespace App\Services\Surveillance;

use App\Repositories\Surveillance\SurveillanceRepository;
use App\Services\Shared\AuditLogService;
use App\Services\Shared\IntegrityRuleEngine;

final class SurveillanceService
{
    public function __construct(private SurveillanceRepository $repository) {}

    /**
     * Récupère le résumé du tableau de bord de surveillance.
     */
    public function getDashboardData(array $filters = []): array
    {
        $alerts = $this->repository->getAlerts($filters);
        $employees = $this->repository->getEmployeesRanking();
        $userId = !empty($filters['user_id']) ? (int) $filters['user_id'] : null;
        $trend = $this->repository->getAlertsTrend($userId);
        $rules = $this->repository->getRulesConfig();

        // Calculer les statistiques du dashboard
        $stats = [
            'total_alerts' => count($alerts),
            'tres_grave' => 0,
            'grave' => 0,
            'moyen' => 0,
            'unresolved' => 0,
        ];

        foreach ($alerts as $alert) {
            if ($alert['statut'] === 'nouvelle' || $alert['statut'] === 'en_cours') {
                $stats['unresolved']++;
            }
            if ($alert['gravite'] === 'tres_grave') {
                $stats['tres_grave']++;
            } elseif ($alert['gravite'] === 'grave') {
                $stats['grave']++;
            } else {
                $stats['moyen']++;
            }
        }

        return [
            'stats' => $stats,
            'alerts' => $alerts,
            'employees' => $employees,
            'trend' => $trend,
            'rules' => $rules,
        ];
    }

    /**
     * Récupère le détail d'une alerte.
     */
    public function getAlertDetail(int $id): ?array
    {
        return $this->repository->getAlertDetail($id);
    }

    /**
     * Traite une alerte d'intégrité.
     */
    public function processAlert(int $id, string $statut, string $commentaire, int $dgUserId): bool
    {
        if (!in_array($statut, ['justifiee', 'confirmee'], true)) {
            throw new \InvalidArgumentException("Statut invalide : {$statut}");
        }

        $alert = $this->repository->getAlertDetail($id);
        if (!$alert) {
            return false;
        }

        $success = $this->repository->updateAlertStatus($id, $statut, $commentaire, $dgUserId);

        if ($success) {
            // Logger l'action de traitement dans l'audit trail
            AuditLogService::log(
                'process_integrity_alert',
                'lbp_alertes_integrite',
                $id,
                ['statut' => $alert['statut'], 'commentaire_dg' => $alert['commentaire_dg']],
                ['statut' => $statut, 'commentaire_dg' => $commentaire]
            );

            // Recalculer le score de l'employé car le changement de statut modifie sa pénalité
            IntegrityRuleEngine::recalculateUserScore((int) $alert['user_id']);
        }

        return $success;
    }

    /**
     * Récupère le profil détaillé d'un employé.
     */
    public function getEmployeeProfile(int $userId): ?array
    {
        $ranking = $this->repository->getEmployeesRanking();
        $employeeData = null;

        foreach ($ranking as $emp) {
            if ((int) $emp['user_id'] === $userId) {
                $employeeData = $emp;
                break;
            }
        }

        if (!$employeeData) {
            return null;
        }

        $history = $this->repository->getEmployeeAlertsHistory($userId);
        $trend = $this->repository->getAlertsTrend($userId);

        return [
            'employee' => $employeeData,
            'alerts_history' => $history,
            'trend' => $trend,
        ];
    }

    /**
     * Récupère la configuration des règles.
     */
    public function getRulesConfig(): array
    {
        return $this->repository->getRulesConfig();
    }

    /**
     * Met à jour la configuration d'une règle.
     */
    public function updateRule(string $code, bool $isActive, string $gravite, array $parameters): bool
    {
        if (!in_array($gravite, ['faible', 'moyen', 'grave', 'tres_grave'], true)) {
            throw new \InvalidArgumentException("Gravité invalide : {$gravite}");
        }

        $oldConfig = null;
        foreach ($this->repository->getRulesConfig() as $rule) {
            if ($rule['code'] === $code) {
                $oldConfig = $rule;
                break;
            }
        }

        $parametresJson = !empty($parameters) ? json_encode($parameters, JSON_UNESCAPED_UNICODE) : null;
        $success = $this->repository->updateRuleConfig($code, $isActive, $gravite, $parametresJson);

        if ($success && $oldConfig) {
            // Logger dans l'audit
            AuditLogService::log(
                'update_integrity_rule_config',
                'lbp_regles_config',
                (int) $oldConfig['id'],
                $oldConfig,
                [
                    'code' => $code,
                    'is_active' => $isActive ? 1 : 0,
                    'gravite' => $gravite,
                    'parametres_json' => $parametresJson
                ]
            );

            // Vider le cache du moteur de règles
            IntegrityRuleEngine::clearCache();
        }

        return $success;
    }
}
