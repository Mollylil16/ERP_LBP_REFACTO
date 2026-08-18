<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Models\Database;
use PDO;
use Throwable;

/**
 * Moteur de règles de détection d'intégrité anti-fraude.
 *
 * Évalue des règles configurables (stockées dans lbp_regles_config) et génère
 * des alertes graduées (moyen / grave / très grave) dans lbp_alertes_integrite.
 * Chaque alerte déclenche un recalcul immédiat du score de l'employé.
 *
 * Les seuils sont chargés dynamiquement : lbp_regles_config.parametres_json
 * a priorité, puis config/surveillance.php, puis les defaults en dur.
 */
final class IntegrityRuleEngine
{
    /** Cache des règles chargées depuis lbp_regles_config. */
    private static ?array $rulesCache = null;

    // ──────────────────────────────────────────────────────────
    // API PUBLIQUE PRINCIPALE
    // ──────────────────────────────────────────────────────────

    /**
     * Évalue une règle et enregistre un signalement si elle est enfreinte.
     *
     * @param string $regleCode   Code de la règle (ex. SOUS_DECLARATION_COLIS)
     * @param int    $userId      ID de l'utilisateur impliqué
     * @param string $entityType  Table/entité concernée (ex. lbp_colis)
     * @param int    $entityId    ID de l'entité concernée
     * @param array  $contexte    Données contextuelles libres (JSON stocké)
     * @param int|null $auditLogId ID de l'entrée audit_log qui a déclenché cette évaluation
     */
    public static function checkRule(
        string $regleCode,
        int $userId,
        string $entityType,
        int $entityId,
        array $contexte = [],
        ?int $auditLogId = null
    ): void {
        try {
            $pdo = Database::getConnection();

            // Charger la règle et vérifier qu'elle est active
            $regle = self::loadRule($regleCode);
            if ($regle === null) {
                return; // Règle inconnue ou désactivée
            }

            $gravite = $regle['gravite'];

            // Éviter les doublons : même user + règle + entité non encore traitée
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM lbp_alertes_integrite
                WHERE user_id = :user_id AND regle_code = :regle_code 
                  AND entity_type = :entity_type AND entity_id = :entity_id 
                  AND statut IN ('nouvelle', 'en_cours')
            ");
            $checkStmt->execute([
                'user_id' => $userId,
                'regle_code' => $regleCode,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            if ((int) $checkStmt->fetchColumn() > 0) {
                return; // Déjà signalé
            }

            // Insérer l'alerte
            $insStmt = $pdo->prepare("
                INSERT INTO lbp_alertes_integrite (
                    user_id, regle_code, gravite, entity_type, entity_id, 
                    contexte, audit_log_id, statut, created_at
                ) VALUES (
                    :user_id, :regle_code, :gravite, :entity_type, :entity_id, 
                    :contexte, :audit_log_id, 'nouvelle', NOW()
                )
            ");

            $insStmt->execute([
                'user_id' => $userId,
                'regle_code' => $regleCode,
                'gravite' => $gravite,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'contexte' => json_encode($contexte, JSON_UNESCAPED_UNICODE),
                'audit_log_id' => $auditLogId,
            ]);

            // Recalculer le score de l'employé
            self::recalculateUserScore($userId);
        } catch (Throwable $e) {
            error_log('[IntegrityRuleEngine] Échec enregistrement alerte: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────
    // ÉVALUATEURS DE RÈGLES SPÉCIFIQUES (appelés depuis les contrôleurs)
    // ──────────────────────────────────────────────────────────

    /**
     * Règle SOUS_DECLARATION_COLIS : valeur/poids < X% de la moyenne historique.
     */
    public static function evaluateSousDeclarationColis(
        int $userId,
        int $colisId,
        float $valeurDeclaree,
        float $poidsTotal,
        ?int $auditLogId = null
    ): void {
        if ($poidsTotal <= 0 || $valeurDeclaree <= 0) {
            return;
        }

        try {
            $pdo = Database::getConnection();
            $params = self::ruleParams('SOUS_DECLARATION_COLIS');
            $seuil = (float) ($params['ratio_minimum_pourcent'] ?? 50);

            // Moyenne historique du prix/kg sur les 180 derniers jours
            $avgStmt = $pdo->query("
                SELECT AVG(valeur_declaree / NULLIF(poids_total, 0)) AS avg_ratio
                FROM lbp_colis
                WHERE poids_total > 0 AND valeur_declaree > 0
                  AND created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
            ");
            $avgRatio = (float) ($avgStmt->fetchColumn() ?: 0);

            if ($avgRatio <= 0) {
                return; // Pas assez de données historiques
            }

            $currentRatio = $valeurDeclaree / $poidsTotal;
            $pourcent = ($currentRatio / $avgRatio) * 100;

            if ($pourcent < $seuil) {
                self::checkRule('SOUS_DECLARATION_COLIS', $userId, 'lbp_colis', $colisId, [
                    'valeur_declaree' => $valeurDeclaree,
                    'poids_total' => $poidsTotal,
                    'ratio_actuel' => round($currentRatio, 2),
                    'ratio_moyen' => round($avgRatio, 2),
                    'pourcentage_vs_moyenne' => round($pourcent, 1),
                    'seuil_applique' => $seuil,
                ], $auditLogId);
            }
        } catch (Throwable $e) {
            error_log('[IntegrityRuleEngine] evaluateSousDeclarationColis: ' . $e->getMessage());
        }
    }

    /**
     * Règle MODIF_POST_VALIDATION : modification d'une entité déjà validée/clôturée.
     */
    public static function evaluateModifPostValidation(
        int $userId,
        string $entityType,
        int $entityId,
        string $statutActuel,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $auditLogId = null
    ): void {
        $params = self::ruleParams('MODIF_POST_VALIDATION');
        $statutsProteges = (array) ($params['statuts_proteges'] ?? [
            'validé', 'clôturé', 'payee', 'soldee', 'cloturee',
        ]);

        $statutNormalize = strtolower(trim($statutActuel));
        foreach ($statutsProteges as $protege) {
            if ($statutNormalize === strtolower(trim($protege))) {
                self::checkRule('MODIF_POST_VALIDATION', $userId, $entityType, $entityId, [
                    'statut_au_moment' => $statutActuel,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                ], $auditLogId);
                return;
            }
        }
    }

    /**
     * Règle CUMUL_ROLES_TRANSACTION : le même user crée, valide ET encaisse.
     *
     * Vérifie dans lbp_audit_logs si le même userId a effectué plusieurs
     * rôles distincts (create, validate, payment) sur la même facture.
     */
    public static function evaluateCumulRoles(
        int $userId,
        int $factureId,
        string $actionCourante,
        ?int $auditLogId = null
    ): void {
        try {
            $pdo = Database::getConnection();
            $params = self::ruleParams('CUMUL_ROLES_TRANSACTION');
            $minRoles = (int) ($params['min_roles_cumules'] ?? 2);

            // Chercher les actions distinctes de ce user sur cette facture
            $stmt = $pdo->prepare("
                SELECT DISTINCT action FROM lbp_audit_logs
                WHERE user_id = :user_id 
                  AND entity_type = 'lbp_factures' 
                  AND entity_id = :entity_id
                  AND action IN ('create', 'create_parcel', 'auto_facturer_colis', 'payment', 'consolidate_cash_report')
            ");
            $stmt->execute([
                'user_id' => $userId,
                'entity_id' => $factureId,
            ]);
            $actionsExistantes = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            // Ajouter l'action courante
            $actionsExistantes[] = $actionCourante;
            $actionsUniques = array_unique($actionsExistantes);

            // Classifier en rôles métier
            $roles = [];
            foreach ($actionsUniques as $a) {
                if (in_array($a, ['create', 'create_parcel', 'auto_facturer_colis'], true)) {
                    $roles['creation'] = true;
                }
                if (in_array($a, ['payment'], true)) {
                    $roles['encaissement'] = true;
                }
                if (in_array($a, ['consolidate_cash_report'], true)) {
                    $roles['validation'] = true;
                }
            }

            if (count($roles) >= $minRoles) {
                self::checkRule('CUMUL_ROLES_TRANSACTION', $userId, 'lbp_factures', $factureId, [
                    'roles_cumules' => array_keys($roles),
                    'actions_detectees' => $actionsUniques,
                    'action_declencheur' => $actionCourante,
                ], $auditLogId);
            }
        } catch (Throwable $e) {
            error_log('[IntegrityRuleEngine] evaluateCumulRoles: ' . $e->getMessage());
        }
    }

    /**
     * Règle SUPPRESSION_HORS_HORAIRES : DELETE/suppression en dehors des heures de bureau.
     */
    public static function evaluateSuppressionHorsHoraires(
        int $userId,
        string $entityType,
        int $entityId,
        ?int $auditLogId = null
    ): void {
        $config = require BASE_PATH . '/config/surveillance.php';
        $heures = $config['heures_bureau'] ?? [];

        $params = self::ruleParams('SUPPRESSION_HORS_HORAIRES');
        $debut = $params['debut'] ?? $heures['debut'] ?? '08:00';
        $fin = $params['fin'] ?? $heures['fin'] ?? '18:00';
        $joursOuvres = $params['jours_ouvres'] ?? $heures['jours_ouvres'] ?? [1, 2, 3, 4, 5];

        $now = new \DateTime();
        $heureCourante = $now->format('H:i');
        $jourSemaine = (int) $now->format('N'); // 1=Lundi, 7=Dimanche

        $horsHoraires = !in_array($jourSemaine, $joursOuvres, true)
            || $heureCourante < $debut
            || $heureCourante > $fin;

        if ($horsHoraires) {
            self::checkRule('SUPPRESSION_HORS_HORAIRES', $userId, $entityType, $entityId, [
                'heure_action' => $now->format('Y-m-d H:i:s'),
                'jour_semaine' => $jourSemaine,
                'plage_autorisee' => "{$debut} - {$fin}",
                'jours_ouvres' => $joursOuvres,
            ], $auditLogId);
        }
    }

    /**
     * Règle ACCES_SURVEILLANCE_NON_AUTORISE : tentative d'accès à une route protégée DG.
     */
    public static function evaluateAccesSurveillanceNonAutorise(
        int $userId,
        string $route,
        ?int $auditLogId = null
    ): void {
        self::checkRule('ACCES_SURVEILLANCE_NON_AUTORISE', $userId, 'surveillance_module', 0, [
            'route_tentee' => $route,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
            'timestamp' => date('Y-m-d H:i:s'),
        ], $auditLogId);
    }

    // ──────────────────────────────────────────────────────────
    // ÉVALUATEURS BATCH (appelés par le cron nocturne)
    // ──────────────────────────────────────────────────────────

    /**
     * Règle ECART_ENCAISSEMENT_COMPTA (batch) : paiements encaissés absents de la comptabilité.
     *
     * Compare lbp_paiements vs lbp_ecritures_comptables sur la période.
     */
    public static function batchEcartEncaissementCompta(): int
    {
        $alertCount = 0;
        try {
            $pdo = Database::getConnection();
            $params = self::ruleParams('ECART_ENCAISSEMENT_COMPTA');
            $delaiHeures = (int) ($params['delai_heures'] ?? 24);

            // Paiements encaissés il y a plus de X heures sans écriture comptable correspondante
            $stmt = $pdo->prepare("
                SELECT p.id, p.facture_id, p.montant, p.mode, p.date_paiement,
                       f.caissiere_id, f.agence_id
                FROM lbp_paiements p
                INNER JOIN lbp_factures f ON p.facture_id = f.id
                LEFT JOIN lbp_ecritures_comptables ec 
                    ON ec.piece_justificative_id = CONCAT('PAI-', p.id)
                WHERE p.date_paiement <= DATE_SUB(NOW(), INTERVAL :delai HOUR)
                  AND ec.id IS NULL
                  AND p.date_paiement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ");
            $stmt->execute(['delai' => $delaiHeures]);
            $paiementsSansCompta = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($paiementsSansCompta as $p) {
                $userId = (int) ($p['caissiere_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                self::checkRule('ECART_ENCAISSEMENT_COMPTA', $userId, 'lbp_paiements', (int) $p['id'], [
                    'facture_id' => (int) $p['facture_id'],
                    'montant' => (float) $p['montant'],
                    'mode_paiement' => $p['mode'],
                    'date_paiement' => $p['date_paiement'],
                    'delai_heures_depasse' => $delaiHeures,
                ]);
                $alertCount++;
            }
        } catch (Throwable $e) {
            error_log('[IntegrityRuleEngine] batchEcartEncaissementCompta: ' . $e->getMessage());
        }
        return $alertCount;
    }

    /**
     * Règle ECART_PESEE_RECURRENT (batch) : écarts poids déclaré vs poids réel récurrents.
     *
     * Compte les colis où l'écart poids > seuil% par agent sur le mois glissant.
     */
    public static function batchEcartPeseeRecurrent(): int
    {
        $alertCount = 0;
        try {
            $pdo = Database::getConnection();
            $params = self::ruleParams('ECART_PESEE_RECURRENT');
            $occurrencesMin = (int) ($params['occurrences_par_mois'] ?? 3);
            $ecartPourcent = (float) ($params['ecart_poids_pourcent'] ?? 15);

            // Agents avec >= N écarts de pesée significatifs sur le mois glissant
            // Compare poids_total (déclaré à l'enregistrement) vs poids_reel (pesé à réception)
            $stmt = $pdo->prepare("
                SELECT c.created_by AS user_id, 
                       COUNT(*) AS nb_ecarts,
                       AVG(ABS(c.poids_total - c.poids_reel) / NULLIF(c.poids_reel, 0) * 100) AS ecart_moyen_pourcent
                FROM lbp_colis c
                WHERE c.poids_reel IS NOT NULL AND c.poids_reel > 0
                  AND c.poids_total > 0 AND c.created_by IS NOT NULL
                  AND ABS(c.poids_total - c.poids_reel) / c.poids_reel * 100 > :seuil_pourcent
                  AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY c.created_by
                HAVING nb_ecarts >= :min_occurrences
            ");
            $stmt->execute([
                'seuil_pourcent' => $ecartPourcent,
                'min_occurrences' => $occurrencesMin,
            ]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                self::checkRule('ECART_PESEE_RECURRENT', (int) $row['user_id'], 'lbp_colis', 0, [
                    'nb_ecarts_mois' => (int) $row['nb_ecarts'],
                    'ecart_moyen_pourcent' => round((float) $row['ecart_moyen_pourcent'], 1),
                    'seuil_occurrences' => $occurrencesMin,
                    'seuil_ecart_pourcent' => $ecartPourcent,
                    'periode' => '30 derniers jours',
                ]);
                $alertCount++;
            }
        } catch (Throwable $e) {
            error_log('[IntegrityRuleEngine] batchEcartPeseeRecurrent: ' . $e->getMessage());
        }
        return $alertCount;
    }

    // ──────────────────────────────────────────────────────────
    // SCORING
    // ──────────────────────────────────────────────────────────

    /**
     * Recalcule le score global d'intégrité d'un employé selon la formule configurable.
     */
    public static function recalculateUserScore(int $userId): void
    {
        try {
            $pdo = Database::getConnection();
            $config = require BASE_PATH . '/config/surveillance.php';
            $scoring = $config['scoring'] ?? [];

            $scoreInitial = (float) ($scoring['score_initial'] ?? 100.00);
            $penMoyen = (float) ($scoring['penalite_moyen'] ?? 5.0);
            $penGrave = (float) ($scoring['penalite_grave'] ?? 20.0);
            $penTresGrave = (float) ($scoring['penalite_tres_grave'] ?? 50.0);
            $statuts = $scoring['statuts_comptabilises'] ?? ['nouvelle', 'en_cours', 'confirmee'];

            $placeholders = implode(',', array_fill(0, count($statuts), '?'));
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN gravite = 'moyen' THEN 1 ELSE 0 END) AS nb_moyen,
                    SUM(CASE WHEN gravite = 'grave' THEN 1 ELSE 0 END) AS nb_grave,
                    SUM(CASE WHEN gravite = 'tres_grave' THEN 1 ELSE 0 END) AS nb_tres_grave
                FROM lbp_alertes_integrite
                WHERE user_id = ? AND statut IN ($placeholders)
            ");
            $stmt->execute(array_merge([$userId], $statuts));
            $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $nbMoyen = (int) ($counts['nb_moyen'] ?? 0);
            $nbGrave = (int) ($counts['nb_grave'] ?? 0);
            $nbTresGrave = (int) ($counts['nb_tres_grave'] ?? 0);

            $score = $scoreInitial - ($nbMoyen * $penMoyen) - ($nbGrave * $penGrave) - ($nbTresGrave * $penTresGrave);
            $score = max(0.0, min(100.0, $score));

            $upStmt = $pdo->prepare("
                INSERT INTO lbp_scores_employes (
                    user_id, score_global, nb_alertes_moyen, nb_alertes_grave, nb_alertes_tres_grave, derniere_maj
                ) VALUES (
                    :user_id, :score, :moyen, :grave, :tres_grave, NOW()
                ) ON DUPLICATE KEY UPDATE 
                    score_global = VALUES(score_global),
                    nb_alertes_moyen = VALUES(nb_alertes_moyen),
                    nb_alertes_grave = VALUES(nb_alertes_grave),
                    nb_alertes_tres_grave = VALUES(nb_alertes_tres_grave),
                    derniere_maj = NOW()
            ");

            $upStmt->execute([
                'user_id' => $userId,
                'score' => $score,
                'moyen' => $nbMoyen,
                'grave' => $nbGrave,
                'tres_grave' => $nbTresGrave,
            ]);

            // Phase 2 : Calculer et mettre à jour le score global combiné (Règles + IA)
            try {
                $mlIntegrationService = new \App\Services\Surveillance\MLIntegrationService();
                $mlIntegrationService->updateCombinedScore($userId);
            } catch (Throwable $mlEx) {
                error_log('[IntegrityRuleEngine] Échec intégration score ML: ' . $mlEx->getMessage());
            }
        } catch (Throwable $e) {
            error_log('[IntegrityRuleEngine] Échec calcul score: ' . $e->getMessage());
        }
    }

    /**
     * Recalcule les scores de TOUS les employés (batch nocturne).
     */
    public static function batchRecalculateAllScores(): int
    {
        $count = 0;
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT DISTINCT user_id FROM lbp_alertes_integrite");
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($userIds as $userId) {
                self::recalculateUserScore((int) $userId);
                $count++;
            }
        } catch (Throwable $e) {
            error_log('[IntegrityRuleEngine] batchRecalculateAllScores: ' . $e->getMessage());
        }
        return $count;
    }

    // ──────────────────────────────────────────────────────────
    // HELPERS INTERNES
    // ──────────────────────────────────────────────────────────

    /**
     * Charge une règle depuis lbp_regles_config (avec cache).
     * Retourne null si la règle n'existe pas ou est désactivée.
     */
    private static function loadRule(string $code): ?array
    {
        if (self::$rulesCache === null) {
            self::$rulesCache = [];
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->query("SELECT * FROM lbp_regles_config WHERE is_active = 1");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    self::$rulesCache[$row['code']] = $row;
                }
            } catch (Throwable $e) {
                error_log('[IntegrityRuleEngine] Impossible de charger les règles: ' . $e->getMessage());
            }
        }

        $regle = self::$rulesCache[$code] ?? null;

        // Fallback : si la règle n'est pas en BDD, utiliser les defaults (pour règles non encore migrées)
        if ($regle === null) {
            $defaults = [
                'ECART_ENCAISSEMENT_COMPTA' => 'tres_grave',
                'ACCES_SURVEILLANCE_NON_AUTORISE' => 'tres_grave',
                'MODIF_POST_VALIDATION' => 'grave',
                'CUMUL_ROLES_TRANSACTION' => 'grave',
                'ECART_PESEE_RECURRENT' => 'grave',
                'SOUS_DECLARATION_COLIS' => 'moyen',
                'SUPPRESSION_HORS_HORAIRES' => 'moyen',
            ];

            if (isset($defaults[$code])) {
                return ['code' => $code, 'gravite' => $defaults[$code], 'is_active' => 1];
            }
            return null;
        }

        return $regle;
    }

    /**
     * Charge les paramètres JSON d'une règle depuis lbp_regles_config, avec
     * fallback sur config/surveillance.php.
     *
     * @return array<string, mixed>
     */
    private static function ruleParams(string $code): array
    {
        $regle = self::loadRule($code);
        $dbParams = [];
        if ($regle !== null && !empty($regle['parametres_json'])) {
            $dbParams = json_decode($regle['parametres_json'], true) ?: [];
        }

        // Merge avec le fichier config comme fallback
        $config = require BASE_PATH . '/config/surveillance.php';
        $fileParams = $config['seuils'][$code] ?? [];

        // BDD a priorité sur fichier config
        return array_merge($fileParams, $dbParams);
    }

    /**
     * Réinitialise le cache des règles (utile après modification en live).
     */
    public static function clearCache(): void
    {
        self::$rulesCache = null;
    }
}
