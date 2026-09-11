<?php

declare(strict_types=1);

namespace App\Repositories\Mobile;

use PDO;

/**
 * Abonnements aux notifications push.
 *
 * Le directeur ouvre l'application sur plusieurs appareils : chacun produit son
 * propre abonnement, et une alerte part vers tous ceux qui sont encore valides.
 */
final class PushSubscriptionRepository implements AbonnementsPushInterface
{
    public function __construct(private PDO $pdo) {}

    public function enregistrer(int $userId, ?int $deviceId, string $endpoint, string $p256dh, string $auth): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_push_subscriptions (user_id, device_id, endpoint, endpoint_hash, p256dh, auth, created_at)
            VALUES (:user_id, :device_id, :endpoint, :hash, :p256dh, :auth, NOW())
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                device_id = VALUES(device_id),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                failure_count = 0
        ");

        $stmt->execute([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'endpoint' => $endpoint,
            'hash' => hash('sha256', $endpoint),
            'p256dh' => $p256dh,
            'auth' => $auth,
        ]);
    }

    public function supprimerParEndpoint(string $endpoint): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM lbp_push_subscriptions WHERE endpoint_hash = :hash");
        $stmt->execute(['hash' => hash('sha256', $endpoint)]);
    }

    public function supprimer(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM lbp_push_subscriptions WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pourUtilisateur(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, endpoint, p256dh, auth
            FROM lbp_push_subscriptions
            WHERE user_id = :user_id
            ORDER BY id ASC
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Abonnements de tous les comptes habilités à recevoir les alertes de direction.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pourDirection(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT s.id, s.user_id, s.endpoint, s.p256dh, s.auth
            FROM lbp_push_subscriptions s
            INNER JOIN users u ON s.user_id = u.id
            LEFT JOIN lbp_user_roles r ON r.user_id = u.id
            WHERE u.status = 'active'
              AND (u.is_admin = 1 OR r.role IN ('dg', 'assistant_dg', 'assistante_dg'))
            ORDER BY s.id ASC
        ");

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function marquerSucces(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_push_subscriptions
            SET last_success_at = NOW(), failure_count = 0
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }

    public function marquerEchec(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_push_subscriptions
            SET last_failure_at = NOW(), failure_count = failure_count + 1
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }
}
