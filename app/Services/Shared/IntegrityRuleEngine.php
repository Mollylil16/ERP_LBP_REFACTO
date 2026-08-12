<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Models\Database;
use PDO;
use Throwable;

final class IntegrityRuleEngine
{
    /**
     * Évalue et enregistre un signalement d'intégrité si une règle est enfreinte.
     */
    public static function checkRule(
        string $regleCode,
        int $userId,
        string $entityType,
        int $entityId,
        array $contexte = []
    ): void {
        try {
            $pdo = Database::getConnection();

            // Vérifier si la règle est active dans lbp_regles_config
            $stmt = $pdo->prepare("SELECT * FROM lbp_regles_config WHERE code = :code AND is_active = 1");
            $stmt->execute(['code' => $regleCode]);
            $regle = $stmt->fetch(PDO::FETCH_ASSOC);

            $gravite = $regle['gravite'] ?? match ($regleCode) {
                'ECART_ENCAISSEMENT_COMPTA', 'ACCES_SURVEILLANCE_NON_AUTORISE' => 'tres_grave',
                'MODIF_POST_VALIDATION', 'CUMUL_ROLES_TRANSACTION', 'ECART_PESEE_RECURRENT' => 'grave',
                default => 'moyen',
            };

            // Éviter les doublons d'alertes identiques non traitées la même journée
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM lbp_alertes_integrite
                WHERE user_id = :user_id AND regle_code = :regle_code AND entity_type = :entity_type 
                  AND entity_id = :entity_id AND statut IN ('nouvelle', 'en_cours')
            ");
            checkStmt:
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
                    user_id, regle_code, gravite, entity_type, entity_id, contexte, statut, created_at
                ) VALUES (
                    :user_id, :regle_code, :gravite, :entity_type, :entity_id, :contexte, 'nouvelle', NOW()
                )
            ");

            $insStmt->execute([
                'user_id' => $userId,
                'regle_code' => $regleCode,
                'gravite' => $gravite,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'contexte' => json_encode($contexte),
            ]);

            // Mettre à jour le score de l'employé
            self::recalculateUserScore($userId);
        } catch (Throwable $e) {
            error_log('[IntegrityRuleEngine] Échec enregistrement alerte: ' . $e->getMessage());
        }
    }

    /**
     * Recalcule le score global d'intégrité d'un employé selon la formule métier.
     */
    public static function recalculateUserScore(int $userId): void
    {
        try {
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN gravite = 'moyen' THEN 1 ELSE 0 END) AS nb_moyen,
                    SUM(CASE WHEN gravite = 'grave' THEN 1 ELSE 0 END) AS nb_grave,
                    SUM(CASE WHEN gravite = 'tres_grave' THEN 1 ELSE 0 END) AS nb_tres_grave
                FROM lbp_alertes_integrite
                WHERE user_id = :user_id AND statut IN ('nouvelle', 'en_cours', 'confirmee')
            ");
            $stmt->execute(['user_id' => $userId]);
            $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $nbMoyen = (int) ($counts['nb_moyen'] ?? 0);
            $nbGrave = (int) ($counts['nb_grave'] ?? 0);
            $nbTresGrave = (int) ($counts['nb_tres_grave'] ?? 0);

            // Formule métier : Score initial 100.00 - pénalités selon sévérité
            $score = 100.00 - ($nbMoyen * 5.0) - ($nbGrave * 20.0) - ($nbTresGrave * 50.0);
            $score = max(0.0, min(100.0, $score));

            $upStmt = $pdo->prepare("
                INSERT INTO lbp_scores_employes (
                    user_id, score_global, nb_alertes_moyen, nb_alertes_grave, nb_alertes_tres_grave, derniere_maj
                ) VALUES (
                    :user_id, :score, :moyen, :grave, :tres_grave, NOW()
                ) ON DUPLICATE KEY UPDATE 
                    score_global = :score,
                    nb_alertes_moyen = :moyen,
                    nb_alertes_grave = :grave,
                    nb_alertes_tres_grave = :tres_grave,
                    derniere_maj = NOW()
            ");

            $upStmt->execute([
                'user_id' => $userId,
                'score' => $score,
                'moyen' => $nbMoyen,
                'grave' => $nbGrave,
                'tres_grave' => $nbTresGrave,
            ]);
        } catch (Throwable $e) {
            error_log('[IntegrityRuleEngine] Échec calcul score: ' . $e->getMessage());
        }
    }
}
