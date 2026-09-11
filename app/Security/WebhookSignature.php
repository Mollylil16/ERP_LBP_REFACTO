<?php

declare(strict_types=1);

namespace App\Security;

use App\Models\Database;
use PDO;
use Throwable;

/**
 * Authentification des appels entrants de machine à machine.
 *
 * Les points d'entrée d'API (callback de paiement, webhook de suivi) ne peuvent pas
 * utiliser le jeton anti-rejeu de session : l'appelant n'est pas un navigateur. Ils
 * étaient de ce fait ouverts à tous. Chaque appel doit désormais porter une signature
 * calculée avec un secret partagé.
 *
 * Schéma attendu, à transmettre à l'opérateur :
 *
 *   X-LBP-Timestamp : horodatage Unix de l'envoi
 *   X-LBP-Signature : sha256=<hmac_sha256(timestamp . "." . corps_brut, secret)>
 *
 * L'horodatage est signé avec le corps et n'est accepté que dans une fenêtre étroite :
 * un appel intercepté ne peut donc pas être rejoué plus tard. La comparaison se fait
 * en temps constant.
 *
 * Le secret se configure par la variable d'environnement LBP_WEBHOOK_SECRET, ou à
 * défaut dans la table lbp_mobile_settings. Tant qu'aucun secret n'est configuré,
 * tout appel est refusé : un point d'entrée non configuré doit être fermé, pas ouvert.
 */
final class WebhookSignature
{
    public const ENTETE_SIGNATURE = 'HTTP_X_LBP_SIGNATURE';
    public const ENTETE_HORODATAGE = 'HTTP_X_LBP_TIMESTAMP';

    /** Fenêtre d'acceptation de l'horodatage, en secondes. */
    private const TOLERANCE = 300;

    private const CLE_REGLAGE = 'webhook_secret';

    /**
     * Vérifie l'appel en cours.
     *
     * @return array{ok: bool, motif: string}
     */
    public static function verifier(string $corpsBrut, ?PDO $pdo = null): array
    {
        $secret = self::secret($pdo);

        if ($secret === null || $secret === '') {
            error_log('[LBP] Appel d\'API refusé : aucun secret de webhook configuré (LBP_WEBHOOK_SECRET).');

            return ['ok' => false, 'motif' => 'Point d\'entrée non configuré.'];
        }

        if (!self::adresseAutorisee()) {
            error_log('[LBP] Appel d\'API refusé : adresse ' . self::adresseAppelant() . ' hors liste autorisée.');

            return ['ok' => false, 'motif' => 'Origine non autorisée.'];
        }

        $signature = (string) ($_SERVER[self::ENTETE_SIGNATURE] ?? '');
        $horodatage = (string) ($_SERVER[self::ENTETE_HORODATAGE] ?? '');

        if ($signature === '' || $horodatage === '') {
            return ['ok' => false, 'motif' => 'Signature ou horodatage absent.'];
        }

        if (!ctype_digit($horodatage)) {
            return ['ok' => false, 'motif' => 'Horodatage invalide.'];
        }

        $ecart = abs(time() - (int) $horodatage);
        if ($ecart > self::TOLERANCE) {
            return ['ok' => false, 'motif' => 'Horodatage hors fenêtre (' . $ecart . ' s d\'écart).'];
        }

        $attendue = 'sha256=' . hash_hmac('sha256', $horodatage . '.' . $corpsBrut, $secret);

        if (!hash_equals($attendue, $signature)) {
            error_log('[LBP] Appel d\'API refusé : signature invalide.');

            return ['ok' => false, 'motif' => 'Signature invalide.'];
        }

        return ['ok' => true, 'motif' => ''];
    }

    /**
     * Calcule la signature d'un corps : sert aux essais et à la documentation
     * remise à l'opérateur.
     *
     * @return array{timestamp: string, signature: string}
     */
    public static function signer(string $corpsBrut, string $secret, ?int $horodatage = null): array
    {
        $horodatage = (string) ($horodatage ?? time());

        return [
            'timestamp' => $horodatage,
            'signature' => 'sha256=' . hash_hmac('sha256', $horodatage . '.' . $corpsBrut, $secret),
        ];
    }

    /**
     * Secret partagé, depuis l'environnement puis depuis la base.
     */
    public static function secret(?PDO $pdo = null): ?string
    {
        $depuisEnv = $_SERVER['LBP_WEBHOOK_SECRET']
            ?? $_ENV['LBP_WEBHOOK_SECRET']
            ?? getenv('LBP_WEBHOOK_SECRET');

        if (is_string($depuisEnv) && $depuisEnv !== '') {
            return $depuisEnv;
        }

        if ($pdo === null) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT valeur FROM lbp_mobile_settings WHERE cle = :cle LIMIT 1');
            $stmt->execute(['cle' => self::CLE_REGLAGE]);
            $valeur = $stmt->fetchColumn();

            return $valeur === false || $valeur === '' ? null : (string) $valeur;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Enregistre un secret en base, à défaut de variable d'environnement.
     */
    public static function definirSecret(string $secret, ?PDO $pdo = null): void
    {
        $pdo ??= Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO lbp_mobile_settings (cle, valeur, updated_at)
            VALUES (:cle, :valeur, NOW())
            ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), updated_at = NOW()
        ");
        $stmt->execute(['cle' => self::CLE_REGLAGE, 'valeur' => $secret]);
    }

    public static function genererSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    // -----------------------------------------------------------------

    /**
     * Liste d'adresses autorisées, optionnelle. Vide, tout appel correctement
     * signé est accepté quelle que soit son origine.
     */
    private static function adresseAutorisee(): bool
    {
        $liste = $_SERVER['LBP_WEBHOOK_IPS'] ?? $_ENV['LBP_WEBHOOK_IPS'] ?? getenv('LBP_WEBHOOK_IPS');

        if (!is_string($liste) || trim($liste) === '') {
            return true;
        }

        $appelant = self::adresseAppelant();

        foreach (explode(',', $liste) as $autorisee) {
            if (trim($autorisee) === $appelant) {
                return true;
            }
        }

        return false;
    }

    private static function adresseAppelant(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnue');
    }
}
