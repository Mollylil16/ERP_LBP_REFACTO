<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Site\WebsiteService;
use App\Repositories\Site\WebsiteRepository;
use App\Repositories\Shared\NotificationRepository;
use App\Services\Shared\NotificationService;
use App\Models\Database;

final class TrackingApiController extends BaseController
{
    private WebsiteService $websiteService;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->websiteService = new WebsiteService(new WebsiteRepository($pdo));
    }

    /**
     * GET /api/v1/tracking/{tracking}
     * Public JSON endpoint for parcel real-time tracking
     */
    public function getTracking(string $tracking): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->websiteService->getRealTrackingData($tracking);

        if ($data === null) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Colis ou expédition introuvable',
                'query' => $tracking,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * POST /api/webhooks/tracking-update
     * Receive or trigger webhook / push status updates for a parcel
     */
    public function webhookUpdate(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;

        $tracking = trim((string) ($input['tracking_number'] ?? $input['numero_tracking'] ?? ''));
        $event = trim((string) ($input['event'] ?? $input['statut'] ?? 'STATUS_UPDATE'));
        $details = trim((string) ($input['details'] ?? $input['message'] ?? ''));

        if ($tracking === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'tracking_number est obligatoire',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pdo = Database::getConnection();
        $colisRepo = new \App\Repositories\Colisage\ColisageRepository($pdo);
        $colis = $colisRepo->findParcelByTracking($tracking);

        if ($colis === null) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Colis introuvable pour le numéro fourni',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $notifRepo = new NotificationRepository($pdo);
        $notifService = new NotificationService($notifRepo);

        $notifService->notifyParcelStatusChange($colis, $colis['statut'] ?? 'EN_TRANSIT', 'Webhook Event: ' . $event . ($details !== '' ? " - {$details}" : ''));

        echo json_encode([
            'success' => true,
            'message' => 'Notification push/webhook transmise et enregistrée',
            'tracking_number' => $tracking,
            'event' => $event,
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
