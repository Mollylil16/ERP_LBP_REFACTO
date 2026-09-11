<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Envoi de notifications push web, sans dépendance externe.
 *
 * Deux normes sont mises en œuvre :
 *  - VAPID (RFC 8292) : le serveur signe un jeton ES256 prouvant son identité auprès du
 *    service de push d'Apple, Google ou Mozilla ;
 *  - Message Encryption aes128gcm (RFC 8291) : le contenu est chiffré de bout en bout
 *    pour le navigateur abonné, le service de push ne peut pas le lire.
 *
 * Le projet ne déclare aucune dépendance de production ; tout repose donc sur les
 * extensions openssl et curl, déjà requises par ailleurs.
 */
final class WebPushService implements EnvoiPushInterface
{
    private const CLE_PUBLIQUE = 'vapid_public_key';
    private const CLE_PRIVEE = 'vapid_private_key';
    private const SUJET = 'vapid_subject';

    public function __construct(private PDO $pdo) {}

    // -----------------------------------------------------------------
    // Clés VAPID
    // -----------------------------------------------------------------

    /**
     * Clé publique à transmettre au navigateur lors de l'abonnement.
     * La paire est générée à la première demande puis réutilisée : la régénérer
     * invaliderait tous les abonnements existants.
     */
    public function clePubliqueVapid(): ?string
    {
        $existante = $this->reglage(self::CLE_PUBLIQUE);
        if ($existante !== null && $existante !== '') {
            return $existante;
        }

        try {
            $this->genererClesVapid();
        } catch (Throwable $e) {
            return null;
        }

        return $this->reglage(self::CLE_PUBLIQUE);
    }

    /**
     * Génère la paire de clés VAPID et la range dans lbp_mobile_settings.
     */
    public function genererClesVapid(): void
    {
        $cle = @openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'private_key_bits' => 384,
        ]);

        if ($cle === false) {
            $detail = '';
            while ($erreur = openssl_error_string()) {
                $detail = $erreur;
            }
            throw new RuntimeException(
                'Génération des clés VAPID impossible : OpenSSL n\'a pas pu créer de clé sur la courbe prime256v1. '
                . 'Vérifiez la variable d\'environnement OPENSSL_CONF du serveur. ' . $detail
            );
        }

        $details = openssl_pkey_get_details($cle);
        if (!isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            throw new RuntimeException('Clé VAPID générée sans coordonnées exploitables.');
        }

        // Format non compressé : 0x04 || X || Y, chaque coordonnée sur 32 octets.
        $publique = "\x04" . $this->bourrer($details['ec']['x']) . $this->bourrer($details['ec']['y']);
        $privee = $this->bourrer($details['ec']['d']);

        $this->definirReglage(self::CLE_PUBLIQUE, self::base64Url($publique));
        $this->definirReglage(self::CLE_PRIVEE, self::base64Url($privee));
    }

    /**
     * Sujet VAPID : une adresse de contact exigée par la norme (mailto: ou https:).
     */
    public function definirSujet(string $sujet): void
    {
        $this->definirReglage(self::SUJET, $sujet);
    }

    // -----------------------------------------------------------------
    // Envoi
    // -----------------------------------------------------------------

    /**
     * Envoie une notification à un abonnement.
     *
     * @param array{endpoint: string, p256dh: string, auth: string} $abonnement
     * @param array<string, mixed> $charge
     * @return array{ok: bool, status: int, expire: bool, message: string}
     */
    public function envoyer(array $abonnement, array $charge, int $ttl = 86400): array
    {
        $publique = $this->reglage(self::CLE_PUBLIQUE);
        $privee = $this->reglage(self::CLE_PRIVEE);

        if ($publique === null || $privee === null) {
            return ['ok' => false, 'status' => 0, 'expire' => false, 'message' => 'Clés VAPID absentes.'];
        }

        $endpoint = (string) $abonnement['endpoint'];
        $corps = json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($corps === false) {
            return ['ok' => false, 'status' => 0, 'expire' => false, 'message' => 'Charge utile non sérialisable.'];
        }

        try {
            $chiffre = $this->chiffrer($corps, (string) $abonnement['p256dh'], (string) $abonnement['auth']);
            $entetes = $this->entetesVapid($endpoint, $publique, $privee);
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 0, 'expire' => false, 'message' => $e->getMessage()];
        }

        $entetes[] = 'Content-Encoding: aes128gcm';
        $entetes[] = 'Content-Type: application/octet-stream';
        $entetes[] = 'TTL: ' . max(0, $ttl);
        $entetes[] = 'Urgency: normal';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $chiffre,
            CURLOPT_HTTPHEADER => $entetes,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);

        $reponse = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $erreurCurl = curl_error($ch);
        curl_close($ch);

        if ($reponse === false) {
            return ['ok' => false, 'status' => 0, 'expire' => false, 'message' => $erreurCurl ?: 'Échec réseau.'];
        }

        // 404 et 410 signifient que l'abonnement n'existe plus côté navigateur :
        // il faut le supprimer, sinon on réessaie indéfiniment.
        $expire = $status === 404 || $status === 410;
        $ok = $status >= 200 && $status < 300;

        return [
            'ok' => $ok,
            'status' => $status,
            'expire' => $expire,
            'message' => $ok ? 'Envoyé.' : ('Refus du service de push (HTTP ' . $status . ').'),
        ];
    }

    // -----------------------------------------------------------------
    // Chiffrement aes128gcm (RFC 8291)
    // -----------------------------------------------------------------

    private function chiffrer(string $contenu, string $p256dhClient, string $authClient): string
    {
        $clePubliqueClient = self::base64UrlDecode($p256dhClient);
        $secretAuth = self::base64UrlDecode($authClient);

        if (strlen($clePubliqueClient) !== 65 || $clePubliqueClient[0] !== "\x04") {
            throw new RuntimeException('Clé publique d\'abonnement invalide.');
        }

        // Paire éphémère propre à ce message.
        $ephemere = @openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'private_key_bits' => 384,
        ]);
        if ($ephemere === false) {
            throw new RuntimeException('Clé éphémère non générée (OpenSSL).');
        }

        $detailsEphemere = openssl_pkey_get_details($ephemere);
        $publiqueEphemere = "\x04"
            . $this->bourrer($detailsEphemere['ec']['x'])
            . $this->bourrer($detailsEphemere['ec']['y']);

        $secretPartage = openssl_pkey_derive($this->clePubliqueDepuisPoint($clePubliqueClient), $ephemere, 32);
        if ($secretPartage === false) {
            throw new RuntimeException('Dérivation ECDH impossible.');
        }

        $sel = random_bytes(16);

        // PRK puis clé de contenu et nonce, conformément a la RFC 8291.
        $info = "WebPush: info\x00" . $clePubliqueClient . $publiqueEphemere;
        $prk = hash_hkdf('sha256', $secretPartage, 32, $info, $secretAuth);

        $cleContenu = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", $sel);
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", $sel);

        // Un seul enregistrement : le contenu est suivi du délimiteur 0x02.
        $chiffre = openssl_encrypt(
            $contenu . "\x02",
            'aes-128-gcm',
            $cleContenu,
            OPENSSL_RAW_DATA,
            $nonce,
            $etiquette
        );

        if ($chiffre === false) {
            throw new RuntimeException('Chiffrement AES-GCM impossible.');
        }

        // En-tête : sel (16) || taille d'enregistrement (4) || longueur clé (1) || clé publique (65)
        return $sel
            . pack('N', 4096)
            . pack('C', strlen($publiqueEphemere))
            . $publiqueEphemere
            . $chiffre
            . $etiquette;
    }

    /**
     * Reconstruit une clé publique OpenSSL à partir d'un point EC non compressé.
     */
    private function clePubliqueDepuisPoint(string $point): \OpenSSLAsymmetricKey
    {
        // En-tête DER d'une clé publique EC sur prime256v1, suivi du point brut.
        $entete = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der = $entete . $point;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $cle = openssl_pkey_get_public($pem);
        if ($cle === false) {
            throw new RuntimeException('Clé publique du navigateur illisible.');
        }

        return $cle;
    }

    // -----------------------------------------------------------------
    // En-têtes VAPID (RFC 8292)
    // -----------------------------------------------------------------

    /**
     * @return array<int, string>
     */
    private function entetesVapid(string $endpoint, string $publique, string $privee): array
    {
        $parts = parse_url($endpoint);
        if (!isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Endpoint de push invalide.');
        }
        $audience = $parts['scheme'] . '://' . $parts['host'];

        $sujet = $this->reglage(self::SUJET) ?: 'mailto:direction@labelleporte.local';

        $entete = self::base64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $charge = self::base64Url((string) json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $sujet,
        ]));

        $aSigner = $entete . '.' . $charge;
        $clePrivee = $this->clePriveeDepuisScalaire(self::base64UrlDecode($privee), self::base64UrlDecode($publique));

        if (!openssl_sign($aSigner, $signatureDer, $clePrivee, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signature VAPID impossible.');
        }

        $jwt = $aSigner . '.' . self::base64Url($this->derVersBrut($signatureDer));

        return [
            'Authorization: vapid t=' . $jwt . ', k=' . $publique,
        ];
    }

    /**
     * Reconstruit la clé privée EC à partir du scalaire et du point public.
     */
    private function clePriveeDepuisScalaire(string $scalaire, string $point): \OpenSSLAsymmetricKey
    {
        // Structure ECPrivateKey (RFC 5915) encodée à la main : version, clé privée,
        // paramètres de courbe (prime256v1) et clé publique.
        $der = "\x30\x77"
            . "\x02\x01\x01"
            . "\x04\x20" . $scalaire
            . "\xa0\x0a" . hex2bin('06082a8648ce3d030107')
            . "\xa1\x44" . "\x03\x42\x00" . $point;

        $pem = "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";

        $cle = openssl_pkey_get_private($pem);
        if ($cle === false) {
            throw new RuntimeException('Clé privée VAPID illisible.');
        }

        return $cle;
    }

    /**
     * Convertit une signature ECDSA DER en paire (r, s) de 32 octets, format attendu par JWS.
     */
    private function derVersBrut(string $der): string
    {
        $position = 0;
        if (($der[$position++] ?? '') !== "\x30") {
            throw new RuntimeException('Signature DER malformée.');
        }

        $longueur = ord($der[$position++]);
        if ($longueur > 0x80) {
            $position += $longueur - 0x80;
        }

        $lire = static function (string $der, int &$position): string {
            if (($der[$position++] ?? '') !== "\x02") {
                throw new RuntimeException('Entier DER attendu.');
            }
            $taille = ord($der[$position++]);
            $valeur = substr($der, $position, $taille);
            $position += $taille;

            $valeur = ltrim($valeur, "\x00");
            return str_pad($valeur, 32, "\x00", STR_PAD_LEFT);
        };

        return $lire($der, $position) . $lire($der, $position);
    }

    // -----------------------------------------------------------------
    // Utilitaires
    // -----------------------------------------------------------------

    private function bourrer(string $coordonnee): string
    {
        return str_pad($coordonnee, 32, "\x00", STR_PAD_LEFT);
    }

    public static function base64Url(string $donnees): string
    {
        return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $donnees): string
    {
        $reste = strlen($donnees) % 4;
        if ($reste !== 0) {
            $donnees .= str_repeat('=', 4 - $reste);
        }

        return (string) base64_decode(strtr($donnees, '-_', '+/'), true);
    }

    private function reglage(string $cle): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT valeur FROM lbp_mobile_settings WHERE cle = :cle LIMIT 1");
            $stmt->execute(['cle' => $cle]);
            $valeur = $stmt->fetchColumn();

            return $valeur === false ? null : (string) $valeur;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function definirReglage(string $cle, string $valeur): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_mobile_settings (cle, valeur, updated_at)
            VALUES (:cle, :valeur, NOW())
            ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), updated_at = NOW()
        ");
        $stmt->execute(['cle' => $cle, 'valeur' => $valeur]);
    }
}
