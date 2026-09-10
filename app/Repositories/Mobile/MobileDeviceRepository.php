<?php

declare(strict_types=1);

namespace App\Repositories\Mobile;

use PDO;

/**
 * Appareils appairés à l'application de direction.
 *
 * Le jeton d'appareil vit dans un cookie du téléphone ; seul son empreinte SHA-256
 * est conservée ici. Une lecture de la base ne permet donc pas de se faire passer
 * pour un téléphone connu, et le code PIN est haché comme un mot de passe.
 */
final class MobileDeviceRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<string, mixed>|null
     */
    public function trouverParJeton(string $jetonEnClair): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.full_name, u.email, u.status
            FROM lbp_mobile_devices d
            INNER JOIN users u ON d.user_id = u.id
            WHERE d.token_hash = :hash AND d.revoked_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['hash' => self::empreinte($jetonEnClair)]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne === false ? null : $ligne;
    }

    /**
     * Appaire un téléphone : enregistre le PIN haché et retourne le jeton en clair,
     * qui n'est jamais réaffiché ensuite.
     */
    public function appairer(int $userId, string $pin, ?string $libelle, ?string $userAgent): string
    {
        $jeton = self::nouveauJeton();

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_mobile_devices (user_id, token_hash, pin_hash, label, user_agent, last_seen_at, created_at)
            VALUES (:user_id, :hash, :pin, :label, :ua, NOW(), NOW())
        ");
        $stmt->execute([
            'user_id' => $userId,
            'hash' => self::empreinte($jeton),
            'pin' => password_hash($pin, PASSWORD_DEFAULT),
            'label' => $libelle !== null ? mb_substr($libelle, 0, 120) : null,
            'ua' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);

        return $jeton;
    }

    public function changerPin(int $deviceId, string $pin): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_mobile_devices
            SET pin_hash = :pin, failed_attempts = 0, locked_until = NULL
            WHERE id = :id
        ");
        $stmt->execute(['id' => $deviceId, 'pin' => password_hash($pin, PASSWORD_DEFAULT)]);
    }

    public function enregistrerEchec(int $deviceId, int $tentatives, ?string $bloqueJusqua): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_mobile_devices
            SET failed_attempts = :tentatives, locked_until = :bloque
            WHERE id = :id
        ");
        $stmt->execute(['id' => $deviceId, 'tentatives' => $tentatives, 'bloque' => $bloqueJusqua]);
    }

    public function enregistrerSucces(int $deviceId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE lbp_mobile_devices
            SET failed_attempts = 0, locked_until = NULL, last_unlocked_at = NOW(), last_seen_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $deviceId]);
    }

    public function toucher(int $deviceId): void
    {
        $stmt = $this->pdo->prepare("UPDATE lbp_mobile_devices SET last_seen_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $deviceId]);
    }

    public function revoquer(int $deviceId): void
    {
        $stmt = $this->pdo->prepare("UPDATE lbp_mobile_devices SET revoked_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $deviceId]);
    }

    /**
     * Appareils actifs d'un utilisateur, pour qu'il puisse révoquer un téléphone perdu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listerPourUtilisateur(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, label, user_agent, last_unlocked_at, last_seen_at, created_at
            FROM lbp_mobile_devices
            WHERE user_id = :user_id AND revoked_at IS NULL
            ORDER BY last_seen_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function nouveauJeton(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function empreinte(string $jeton): string
    {
        return hash('sha256', $jeton);
    }
}
