<?php

declare(strict_types=1);

namespace App\Services\Surveillance;

use App\Models\Database;
use App\Services\Shared\AuditLogService;
use PDO;
use Exception;

final class MLIntegrationService
{
    private string $apiUrl;

    public function __construct()
    {
        // En Option A local, FastAPI tourne sur http://127.0.0.1:8000
        $this->apiUrl = 'http://127.0.0.1:8000';
    }

    /**
     * Appelle le service Python pour prédire les scores de risque d'un employé.
     */
    public function predictRisk(int $userId): ?array
    {
        try {
            $url = "{$this->apiUrl}/predict";
            $payload = json_encode(['user_id' => $userId]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout court pour ne pas bloquer l'ERP

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                return json_decode($response, true);
            }
        } catch (Exception $e) {
            error_log("[MLIntegrationService] predictRisk error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Déclenche le réentraînement des modèles ML en tâche de fond.
     */
    public function trainModels(): ?array
    {
        try {
            $url = "{$this->apiUrl}/train";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // L'entraînement peut prendre un peu plus de temps

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                return json_decode($response, true);
            }
        } catch (Exception $e) {
            error_log("[MLIntegrationService] trainModels error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Calcule et met à jour le score global combiné (Règles + IA) d'un employé.
     * Formule : Combined_Score = 0.6 * Score_Règles + 0.4 * (100 - Score_Risk_ML)
     */
    public function updateCombinedScore(int $userId): float
    {
        $pdo = Database::getConnection();

        // 1. Récupérer le score règles existant (depuis lbp_scores_employes)
        $rulesStmt = $pdo->prepare("SELECT score_global FROM lbp_scores_employes WHERE user_id = :user_id");
        $rulesStmt->execute(['user_id' => $userId]);
        $scoreRules = $rulesStmt->fetchColumn();
        
        if ($scoreRules === false) {
            $scoreRules = 100.00; // Pas d'alertes par défaut
        } else {
            $scoreRules = (float) $scoreRules;
        }

        // 2. Récupérer le dernier score ML. Si absent, lancer la prédiction
        $mlStmt = $pdo->prepare("
            SELECT score_final FROM lbp_scores_ml_employes 
            WHERE user_id = :user_id 
            ORDER BY date_calcul DESC LIMIT 1
        ");
        $mlStmt->execute(['user_id' => $userId]);
        $scoreMl = $mlStmt->fetchColumn();

        if ($scoreMl === false) {
            // Pas de score ML en base, appeler l'API
            $predict = $this->predictRisk($userId);
            $scoreMl = $predict ? (float) $predict['score_final'] : 0.0;
        } else {
            $scoreMl = (float) $scoreMl;
        }

        // 3. Fusionner les scores
        // On convertit le score de risque ML (100 = risqué, 0 = sûr) en score de fiabilité (100 = sûr)
        $scoreMlFiabilite = 100.0 - $scoreMl;
        
        $combinedScore = (0.6 * $scoreRules) + (0.4 * $scoreMlFiabilite);
        $combinedScore = max(0.0, min(100.0, $combinedScore));

        // 4. Mettre à jour la table lbp_scores_employes
        $upStmt = $pdo->prepare("
            UPDATE lbp_scores_employes
            SET score_global = :score
            WHERE user_id = :user_id
        ");
        $upStmt->execute([
            'score' => $combinedScore,
            'user_id' => $userId
        ]);

        return $combinedScore;
    }
}
