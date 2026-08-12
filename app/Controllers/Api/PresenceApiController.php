<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Models\Database;
use App\Services\Shared\IntegrityRuleEngine;
use PDO;
use Throwable;

final class PresenceApiController extends BaseController
{
    /**
     * Reçoit les coordonnées GPS envoyées automatiquement par le navigateur de l'employé connecté.
     */
    public function pingGps(): void
    {
        header('Content-Type: application/json');

        if (!Auth::check()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            return;
        }

        $userId = Auth::id();
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true) ?: $_POST;

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;
        $accuracy = isset($data['accuracy']) ? (float) $data['accuracy'] : null;

        if ($lat === null || $lng === null) {
            echo json_encode(['success' => false, 'message' => 'Coordonnées invalides']);
            return;
        }

        try {
            $pdo = Database::getConnection();

            // Récupérer l'employé et son agence assignée
            $stmt = $pdo->prepare("
                SELECT e.id AS employee_id, e.site_id, s.name AS site_name, s.latitude AS site_lat, s.longitude AS site_lng
                FROM rh_employees e
                LEFT JOIN company_sites s ON e.site_id = s.id
                WHERE e.user_id = :user_id OR e.id = :user_id
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $userId]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);

            $siteId = $emp['site_id'] ?? null;
            $employeeId = $emp['employee_id'] ?? null;
            $siteLat = isset($emp['site_lat']) ? (float) $emp['site_lat'] : null;
            $siteLng = isset($emp['site_lng']) ? (float) $emp['site_lng'] : null;

            // Calcul de la distance Haversine en kilomètres
            $distanceKm = null;
            $statut = 'sur_site';

            if ($siteLat !== null && $siteLng !== null && (abs($siteLat) > 0.0001 || abs($siteLng) > 0.0001)) {
                $distanceKm = self::haversineDistance($lat, $lng, $siteLat, $siteLng);
                if ($distanceKm <= 0.200) {
                    $statut = 'sur_site';
                } elseif ($distanceKm <= 1.000) {
                    $statut = 'proximite';
                } else {
                    $statut = 'hors_site';
                }
            }

            // Insérer dans lbp_employee_presence_gps
            $insStmt = $pdo->prepare("
                INSERT INTO lbp_employee_presence_gps (
                    user_id, employee_id, site_id, latitude, longitude, accuracy, 
                    distance_site_km, statut_presence, ip_address, user_agent, created_at
                ) VALUES (
                    :user_id, :employee_id, :site_id, :lat, :lng, :accuracy, 
                    :distance_km, :statut, :ip, :ua, NOW()
                )
            ");

            $insStmt->execute([
                'user_id' => $userId,
                'employee_id' => $employeeId,
                'site_id' => $siteId,
                'lat' => $lat,
                'lng' => $lng,
                'accuracy' => $accuracy,
                'distance_km' => $distanceKm,
                'statut' => $statut,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
            ]);

            // Si l'employé est Hors Site pendant les heures de travail, déclencher un signalement d'intégrité pour le DG
            if ($statut === 'hors_site' && $distanceKm > 1.5) {
                IntegrityRuleEngine::checkRule(
                    'ACCES_HORS_SITE',
                    $userId,
                    'user',
                    $userId,
                    [
                        'distance_km' => round($distanceKm, 2),
                        'site_name' => $emp['site_name'] ?? 'Agence',
                        'emp_lat' => $lat,
                        'emp_lng' => $lng,
                    ]
                );
            }

            echo json_encode([
                'success' => true,
                'statut' => $statut,
                'distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null,
                'message' => 'Géolocalisation de présence enregistrée avec succès'
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Formule de Haversine pour calculer la distance en km entre deux points GPS.
     */
    private static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Rayon de la Terre en km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
