<?php

declare(strict_types=1);

namespace App\Repositories\Surveillance;

use PDO;

final class SurveillanceRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Récupère les alertes filtrées et triées par gravité décroissante.
     * Les statuts autorisés : nouvelle, en_cours, justifiee, confirmee.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getAlerts(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['statut'])) {
            $conditions[] = "a.statut = :statut";
            $params['statut'] = $filters['statut'];
        } else {
            // Par défaut, afficher les alertes non traitées
            $conditions[] = "a.statut IN ('nouvelle', 'en_cours')";
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = "a.user_id = :user_id";
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['regle_code'])) {
            $conditions[] = "a.regle_code = :regle_code";
            $params['regle_code'] = $filters['regle_code'];
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = "a.created_at >= :start_date";
            $params['start_date'] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = "a.created_at <= :end_date";
            $params['end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Tri par gravité (tres_grave = 1, grave = 2, moyen = 3) puis date_creation desc
        $sql = "
            SELECT a.*, 
                   u.full_name AS user_name, u.email AS user_email,
                   r.titre AS regle_titre, r.description AS regle_desc
            FROM lbp_alertes_integrite a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN lbp_regles_config r ON a.regle_code = r.code
            {$where}
            ORDER BY 
                CASE a.gravite 
                    WHEN 'tres_grave' THEN 1 
                    WHEN 'grave' THEN 2 
                    WHEN 'moyen' THEN 3 
                    ELSE 4 
                END ASC,
                a.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Récupère le détail d'une alerte avec son contexte et l'historique de l'utilisateur.
     */
    public function getAlertDetail(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, 
                   u.full_name AS user_name, u.email AS user_email, u.phone AS user_phone,
                   r.titre AS regle_titre, r.description AS regle_desc,
                   ut.full_name AS DG_name,
                   al.ip_address AS audit_ip, al.user_agent AS audit_ua, al.hash_courant AS audit_hash
            FROM lbp_alertes_integrite a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN lbp_regles_config r ON a.regle_code = r.code
            LEFT JOIN users ut ON a.traite_par = ut.id
            LEFT JOIN lbp_audit_logs al ON a.audit_log_id = al.id
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alert) {
            return null;
        }

        // Décoder le JSON
        $alert['contexte'] = json_decode($alert['contexte'] ?? '', true) ?: [];

        return $alert;
    }

    /**
     * Met à jour le statut d'une alerte (justifiée ou confirmée) par le DG.
     */
    public function updateAlertStatus(int $id, string $statut, string $commentaire, int $dgUserId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_alertes_integrite
            SET statut = :statut,
                commentaire_dg = :commentaire,
                traite_par = :dg_user_id,
                traite_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'statut' => $statut,
            'commentaire' => $commentaire,
            'dg_user_id' => $dgUserId,
            'id' => $id,
        ]);
    }

    /**
     * Classement des employés par score d'intégrité global.
     */
    public function getEmployeesRanking(): array
    {
        $sql = "
            SELECT u.id AS user_id, u.full_name, u.email,
                   COALESCE(s.score_global, 100.00) AS score_global,
                   COALESCE(s.nb_alertes_moyen, 0) AS nb_moyen,
                   COALESCE(s.nb_alertes_grave, 0) AS nb_grave,
                   COALESCE(s.nb_alertes_tres_grave, 0) AS nb_tres_grave,
                   s.derniere_maj
            FROM users u
            LEFT JOIN lbp_scores_employes s ON u.id = s.user_id
            WHERE u.status = 'active'
            ORDER BY score_global DESC, u.full_name ASC
        ";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Récupère l'historique complet des alertes pour un employé donné.
     */
    public function getEmployeeAlertsHistory(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, r.titre AS regle_titre
            FROM lbp_alertes_integrite a
            LEFT JOIN lbp_regles_config r ON a.regle_code = r.code
            WHERE a.user_id = :user_id
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Tendance des alertes par mois pour un employé ou globale.
     * Retourne les 6 derniers mois.
     */
    public function getAlertsTrend(?int $userId = null): array
    {
        $conditions = ["a.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)"];
        $params = [];

        if ($userId !== null) {
            $conditions[] = "a.user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "
            SELECT DATE_FORMAT(a.created_at, '%Y-%m') AS mois,
                   SUM(CASE WHEN a.gravite = 'tres_grave' THEN 1 ELSE 0 END) AS tres_grave,
                   SUM(CASE WHEN a.gravite = 'grave' THEN 1 ELSE 0 END) AS grave,
                   SUM(CASE WHEN a.gravite = 'moyen' THEN 1 ELSE 0 END) AS moyen,
                   COUNT(*) AS total
            FROM lbp_alertes_integrite a
            {$where}
            GROUP BY DATE_FORMAT(a.created_at, '%Y-%m')
            ORDER BY mois ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Récupère les règles configurables.
     */
    public function getRulesConfig(): array
    {
        return $this->pdo->query("SELECT * FROM lbp_regles_config ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Met à jour une règle de configuration.
     */
    public function updateRuleConfig(string $code, bool $isActive, string $gravite, ?string $parametresJson): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_regles_config
            SET is_active = :is_active,
                gravite = :gravite,
                parametres_json = :parametres_json
            WHERE code = :code
        ");
        return $stmt->execute([
            'is_active' => $isActive ? 1 : 0,
            'gravite' => $gravite,
            'parametres_json' => $parametresJson,
            'code' => $code,
        ]);
    }
}
