<?php

declare(strict_types=1);

namespace App\Repositories\Shared;

use PDO;

class NotificationRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @param array<string, mixed> $data
     */
    public function createNotification(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_notifications (colis_id, destinataire_telephone, destinataire_email, type_notification, statut, message, created_at)
            VALUES (:colis_id, :destinataire_telephone, :destinataire_email, :type_notification, :statut, :message, NOW())
        ");
        $stmt->execute([
            'colis_id' => (int) ($data['colis_id'] ?? 0),
            'destinataire_telephone' => isset($data['destinataire_telephone']) ? (string) $data['destinataire_telephone'] : null,
            'destinataire_email' => isset($data['destinataire_email']) ? (string) $data['destinataire_email'] : null,
            'type_notification' => (string) ($data['type_notification'] ?? 'ARRIVEE_AGENCE'),
            'statut' => (string) ($data['statut'] ?? 'ENVOYÉ'),
            'message' => (string) ($data['message'] ?? ''),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getNotificationsForColis(int $colisId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM lbp_notifications
            WHERE colis_id = :colis_id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['colis_id' => $colisId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
